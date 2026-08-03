<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Quiz publish service for local_espace (Quiz Studio Phase 0).
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use context_module;
use core_question\category_manager;
use core_question\local\bank\question_bank_helper;
use core_question\local\bank\question_version_status;
use local_espace\output\ApiResponse;
use local_espace\validator\SectionValidator;
use mod_quiz\grade_calculator;
use mod_quiz\quiz_settings;
use moodle_exception;
use question_bank;
use stdClass;

/**
 * Publish ESPACE quiz payloads into mod_quiz via Moodle core APIs.
 */
final class QuizService extends BaseService {

  /** @var SectionValidator */
  private SectionValidator $sectionvalidator;

  public function __construct(
    ?\local_espace\permission\CapabilityChecker $permissions = null,
    ?\local_espace\helper\ModuleHelper $modules = null,
    ?\local_espace\validator\CourseValidator $coursevalidator = null,
    ?SectionValidator $sectionvalidator = null
  ) {
    parent::__construct($permissions, $modules, $coursevalidator);
    $this->sectionvalidator = $sectionvalidator ?? new SectionValidator($this->modules, $this->coursevalidator);
  }

  /**
   * Capability matrix for this subsystem.
   *
   * @return array
   */
  public function capability_matrix(): array {
    $this->permissions->require_login();
    $this->permissions->require_plugin_enabled();

    return ApiResponse::success('quiz_capability_matrix', [
      'use_official_ws' => [
        'mod_quiz_get_quizzes_by_courses',
        'mod_quiz_start_attempt',
        'mod_quiz_process_attempt',
        'mod_quiz_get_attempt_review',
      ],
      'local_espace_gap' => 'local_espace_publish_quiz',
      'status' => 'phase0_publish',
    ]);
  }

  /**
   * Create a quiz activity and attach questions from an ESPACE payload.
   *
   * @param int $courseid
   * @param int $sectionid
   * @param array $payload
   * @return array
   */
  public function publish(int $courseid, int $sectionid, array $payload): array {
    global $CFG, $DB, $PAGE;

    require_once($CFG->dirroot . '/course/modlib.php');
    require_once($CFG->dirroot . '/mod/quiz/lib.php');
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');

    $this->permissions->require_module_management($courseid);
    $course = $this->coursevalidator->require_course($courseid);
    [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);

    $title = trim((string) ($payload['title'] ?? ''));
    if ($title === '') {
      throw new moodle_exception('errorrequiredfield', 'local_espace', '', 'title');
    }

    $questions = $payload['questions'] ?? [];
    if (!is_array($questions) || count($questions) < 1) {
      throw new moodle_exception('errorquizpublishnoquestions', 'local_espace');
    }

    $this->validate_questions_payload($questions);

    $PAGE->set_context(\context_course::instance($courseid));

    $intro = $this->rich_text_field($payload['intro'] ?? []);

    $transaction = $DB->start_delegated_transaction();

    try {
      [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'quiz', (int) $section->section);
      $moduleinfo->modulename = 'quiz';
      $moduleinfo->add = 'quiz';
      $moduleinfo->section = (int) $section->section;
      $this->apply_quiz_create_defaults($moduleinfo);
      $moduleinfo->name = $title;
      if (isset($moduleinfo->introeditor)) {
        $moduleinfo->introeditor['text'] = $intro['text'];
        $moduleinfo->introeditor['format'] = $intro['format'];
      } else {
        $moduleinfo->intro = $intro['text'];
        $moduleinfo->introformat = $intro['format'];
      }

      $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
      $cmid = (int) $moduleinfo->coursemodule;
      $quiz = $DB->get_record('quiz', ['id' => $moduleinfo->instance], '*', MUST_EXIST);
      $quiz->cmid = $cmid;

      $modcontext = context_module::instance($cmid);
      require_capability('mod/quiz:manage', $modcontext);

      [$category, $bankcontext] = $this->resolve_question_category($course);

      $saved = [];
      foreach ($questions as $index => $questionpayload) {
        if (!is_array($questionpayload)) {
          throw new moodle_exception('errorquizpublishinvalidquestion', 'local_espace', '', (string) $index);
        }
        $questionid = $this->create_question($questionpayload, (int) $category->id, $bankcontext);
        quiz_require_question_use($questionid);
        quiz_add_quiz_question($questionid, $quiz);
        $saved[] = [
          'index' => $index,
          'type' => (string) ($questionpayload['type'] ?? ''),
          'questionid' => $questionid,
        ];
      }

      $quizsettings = quiz_settings::create($quiz->id);
      $gradecalculator = $quizsettings->get_grade_calculator();
      $gradecalculator->recompute_quiz_sumgrades();
      $quizrecord = $quizsettings->get_quiz();
      if ($quizrecord->sumgrades > grade_calculator::ALMOST_ZERO) {
        $gradecalculator->update_quiz_maximum_grade((float) $quizrecord->sumgrades);
      }

      $transaction->allow_commit();
    } catch (\Throwable $e) {
      $transaction->rollback($e);
      throw $e;
    }

    $this->modules->rebuild_course_cache($courseid);
    $cminfo = $this->modules->get_cm($cmid);

    return ApiResponse::success('publish_quiz', [
      'cm' => $this->modules->export_cm($cminfo),
      'courseid' => $courseid,
      'quizid' => (int) $quiz->id,
      'questions' => $saved,
    ]);
  }

  /**
   * Apply mod_quiz form-equivalent defaults before add_moduleinfo().
   *
   * Mirrors mod/quiz/tests/generator/lib.php create_instance() so quiz_process_options()
   * receives quizpassword and other NOT NULL columns (password, subnet, preferredbehaviour, …).
   *
   * @param stdClass $moduleinfo Moduleinfo from prepare_new_moduleinfo_data().
   * @return void
   */
  private function apply_quiz_create_defaults(stdClass $moduleinfo): void {
    $quizconfig = get_config('quiz');

    $defaults = [
      'timeopen' => 0,
      'timeclose' => 0,
      'timelimit' => 0,
      'overduehandling' => 'autosubmit',
      'graceperiod' => isset($quizconfig->graceperiod) ? (int) $quizconfig->graceperiod : 86400,
      'preferredbehaviour' => 'deferredfeedback',
      'attempts' => 0,
      'attemptonlast' => 0,
      'grademethod' => QUIZ_GRADEHIGHEST,
      'decimalpoints' => 2,
      'questiondecimalpoints' => -1,
      'attemptduring' => 1,
      'correctnessduring' => 1,
      'maxmarksduring' => 1,
      'marksduring' => 1,
      'specificfeedbackduring' => 1,
      'generalfeedbackduring' => 1,
      'rightanswerduring' => 1,
      'overallfeedbackduring' => 0,
      'attemptimmediately' => 1,
      'correctnessimmediately' => 1,
      'maxmarksimmediately' => 1,
      'marksimmediately' => 1,
      'specificfeedbackimmediately' => 1,
      'generalfeedbackimmediately' => 1,
      'rightanswerimmediately' => 1,
      'overallfeedbackimmediately' => 1,
      'attemptopen' => 1,
      'correctnessopen' => 1,
      'maxmarksopen' => 1,
      'marksopen' => 1,
      'specificfeedbackopen' => 1,
      'generalfeedbackopen' => 1,
      'rightansweropen' => 1,
      'overallfeedbackopen' => 1,
      'attemptclosed' => 1,
      'correctnessclosed' => 1,
      'maxmarksclosed' => 1,
      'marksclosed' => 1,
      'specificfeedbackclosed' => 1,
      'generalfeedbackclosed' => 1,
      'rightanswerclosed' => 1,
      'overallfeedbackclosed' => 1,
      'questionsperpage' => isset($quizconfig->questionsperpage) ? (int) $quizconfig->questionsperpage : 1,
      'shuffleanswers' => 1,
      'sumgrades' => 0,
      'grade' => isset($quizconfig->maximumgrade) ? (float) $quizconfig->maximumgrade : 100,
      'quizpassword' => '',
      'subnet' => '',
      'browsersecurity' => '',
      'delay1' => 0,
      'delay2' => 0,
      'showuserpicture' => 0,
      'showblocks' => 0,
      'navmethod' => QUIZ_NAVMETHOD_FREE,
      'canredoquestions' => 0,
    ];

    foreach ($defaults as $name => $value) {
      if (!property_exists($moduleinfo, $name) || $moduleinfo->$name === null) {
        $moduleinfo->$name = $value;
      }
    }
  }

  /**
   * @param stdClass $course
   * @return array{0:stdClass,1:\context}
   */
  private function resolve_question_category(stdClass $course): array {
    global $DB;

    $qbankcm = question_bank_helper::get_default_open_instance_system_type($course, true);
    if (!$qbankcm) {
      throw new moodle_exception('errorquizpublishnobank', 'local_espace');
    }

    $bankcontext = context_module::instance($qbankcm->id);
    require_capability('moodle/question:add', $bankcontext);

    $category = question_get_default_category($bankcontext->id);
    if (!$category) {
      require_capability('moodle/question:managecategory', $bankcontext);
      $parent = question_get_top_category($bankcontext->id, true);
      $manager = new category_manager();
      $categoryid = $manager->add_category(
        $parent->id . ',' . $bankcontext->id,
        get_string('questions', 'question'),
        '',
        (string) FORMAT_HTML,
      );
      $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
    }

    question_require_capability_on('moodle/question:add', $category);

    return [$category, $bankcontext];
  }

  /**
   * @param array $payload
   * @param int $categoryid
   * @param \context $bankcontext
   * @return int question id
   */
  private function create_question(array $payload, int $categoryid, \context $bankcontext): int {
    $type = strtolower(trim((string) ($payload['type'] ?? '')));
    if ($type === 'multiple_choice') {
      $fromform = $this->multichoice_fromform($payload, $categoryid);
      $qtype = 'multichoice';
    } else if ($type === 'short_answer') {
      $fromform = $this->shortanswer_fromform($payload, $categoryid);
      $qtype = 'shortanswer';
    } else {
      throw new moodle_exception('errorquizpublishunsupportedqtype', 'local_espace', '', $type);
    }

    $question = new stdClass();
    $question->category = $categoryid;
    $question->qtype = $qtype;
    $question->contextid = $bankcontext->id;

    $saved = question_bank::get_qtype($qtype)->save_question($question, $fromform);
    return (int) $saved->id;
  }

  /**
   * @param array $payload
   * @param int $categoryid
   * @return stdClass
   */
  private function multichoice_fromform(array $payload, int $categoryid): stdClass {
    $stem = $this->rich_text_field($payload['stem'] ?? []);
    $choices = $payload['choices'] ?? [];
    if (!is_array($choices) || count($choices) < 2) {
      throw new moodle_exception('errorquizpublishmcqchoices', 'local_espace');
    }

    $form = new stdClass();
    $form->category = $categoryid;
    $form->name = shorten_text(strip_tags($stem['text']), 80);
    if ($form->name === '') {
      $form->name = get_string('question');
    }
    $form->questiontext = $stem;
    $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->defaultmark = isset($payload['mark']) ? (float) $payload['mark'] : 1.0;
    $form->penalty = 0.3333333;
    $form->status = question_version_status::QUESTION_STATUS_READY;
    $form->single = '1';
    $form->shuffleanswers = 1;
    $form->answernumbering = 'abc';
    $form->showstandardinstruction = 0;
    $form->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->shownumcorrect = 0;
    $form->numhints = 0;
    $form->hint = [];
    $form->hintclearwrong = [];
    $form->hintshownumcorrect = [];

    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];
    $correctcount = 0;
    foreach ($choices as $choice) {
      if (!is_array($choice)) {
        continue;
      }
      $textfield = $this->rich_text_field($choice['text'] ?? [], '');
      if (trim($textfield['text']) === '') {
        continue;
      }
      $iscorrect = !empty($choice['correct']);
      if ($iscorrect) {
        $correctcount++;
      }
      $idx = count($form->answer);
      $form->answer[$idx] = $textfield;
      $form->fraction[$idx] = $iscorrect ? '1.0' : '0.0';
      $form->feedback[$idx] = ['text' => '', 'format' => FORMAT_HTML];
    }

    if (count($form->answer) < 2 || $correctcount !== 1) {
      throw new moodle_exception('errorquizpublishmcqchoices', 'local_espace');
    }

    $form->noanswers = count($form->answer) + 1;

    return $form;
  }

  /**
   * @param array $payload
   * @param int $categoryid
   * @return stdClass
   */
  private function shortanswer_fromform(array $payload, int $categoryid): stdClass {
    $stem = $this->rich_text_field($payload['stem'] ?? []);
    $answers = $payload['answers'] ?? [];
    if (!is_array($answers) || count($answers) < 1) {
      throw new moodle_exception('errorquizpublishshortanswers', 'local_espace');
    }

    $form = new stdClass();
    $form->category = $categoryid;
    $form->name = shorten_text(strip_tags($stem['text']), 80);
    if ($form->name === '') {
      $form->name = get_string('question');
    }
    $form->questiontext = $stem;
    $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->defaultmark = isset($payload['mark']) ? (float) $payload['mark'] : 1.0;
    $form->penalty = 0.3333333;
    $form->status = question_version_status::QUESTION_STATUS_READY;
    $form->usecase = !empty($payload['case_sensitive']);
    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];

    foreach ($answers as $answer) {
      if (!is_array($answer)) {
        continue;
      }
      $text = trim((string) ($answer['text'] ?? ''));
      if ($text === '') {
        continue;
      }
      $idx = count($form->answer);
      $form->answer[$idx] = $text;
      $fraction = isset($answer['fraction']) ? (float) $answer['fraction'] : 1.0;
      $form->fraction[$idx] = (string) $fraction;
      $form->feedback[$idx] = ['text' => '', 'format' => FORMAT_HTML];
    }

    if (count($form->answer) < 1) {
      throw new moodle_exception('errorquizpublishshortanswers', 'local_espace');
    }

    return $form;
  }

  /**
   * Validate the full questions array before any Moodle writes.
   *
   * @param array $questions
   * @return void
   */
  private function validate_questions_payload(array $questions): void {
    foreach ($questions as $index => $questionpayload) {
      if (!is_array($questionpayload)) {
        throw new moodle_exception('errorquizpublishinvalidquestion', 'local_espace', '', (string) $index);
      }

      $stem = $this->rich_text_field($questionpayload['stem'] ?? []);
      if (trim($stem['text']) === '') {
        throw new moodle_exception('errorquizpublishemptystem', 'local_espace', '', (string) $index);
      }

      $mark = isset($questionpayload['mark']) ? (float) $questionpayload['mark'] : 1.0;
      if ($mark <= 0) {
        throw new moodle_exception('errorquizpublishinvalidmark', 'local_espace', '', (string) $index);
      }

      $type = strtolower(trim((string) ($questionpayload['type'] ?? '')));
      if ($type === 'multiple_choice') {
        $this->validate_mcq_payload($questionpayload);
      } else if ($type === 'short_answer') {
        $this->validate_shortanswer_payload($questionpayload);
      } else {
        throw new moodle_exception('errorquizpublishunsupportedqtype', 'local_espace', '', $type);
      }
    }
  }

  /**
   * @param array $payload
   * @return void
   */
  private function validate_mcq_payload(array $payload): void {
    $choices = $payload['choices'] ?? [];
    if (!is_array($choices)) {
      throw new moodle_exception('errorquizpublishmcqchoices', 'local_espace');
    }

    $nonempty = 0;
    $correctcount = 0;
    foreach ($choices as $choice) {
      if (!is_array($choice)) {
        continue;
      }
      $textfield = $this->rich_text_field($choice['text'] ?? [], '');
      if (trim($textfield['text']) === '') {
        continue;
      }
      $nonempty++;
      if (!empty($choice['correct'])) {
        $correctcount++;
      }
    }

    if ($nonempty < 2 || $correctcount !== 1) {
      throw new moodle_exception('errorquizpublishmcqchoices', 'local_espace');
    }
  }

  /**
   * @param array $payload
   * @return void
   */
  private function validate_shortanswer_payload(array $payload): void {
    $answers = $payload['answers'] ?? [];
    if (!is_array($answers)) {
      throw new moodle_exception('errorquizpublishshortanswers', 'local_espace');
    }

    $nonempty = 0;
    foreach ($answers as $answer) {
      if (!is_array($answer)) {
        continue;
      }
      if (trim((string) ($answer['text'] ?? '')) !== '') {
        $nonempty++;
      }
    }

    if ($nonempty < 1) {
      throw new moodle_exception('errorquizpublishshortanswers', 'local_espace');
    }
  }

  /**
   * @param array $field
   * @param string $defaulttext
   * @return array{text:string,format:int}
   */
  private function rich_text_field(array $field, string $defaulttext = ''): array {
    $text = array_key_exists('text', $field) ? (string) $field['text'] : $defaulttext;
    $format = $this->resolve_text_format((string) ($field['format'] ?? 'html'));
    return ['text' => $text, 'format' => $format];
  }

  /**
   * @param string $format
   * @return int
   */
  private function resolve_text_format(string $format): int {
    switch (strtolower($format)) {
      case 'plain':
        return (int) FORMAT_PLAIN;
      case 'markdown':
        return (int) FORMAT_MARKDOWN;
      case 'moodle':
        return (int) FORMAT_MOODLE;
      default:
        return (int) FORMAT_HTML;
    }
  }
}
