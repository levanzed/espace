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
 * Assignment service for local_espace.
 *
 * Sprint A: teacher authoring via Moodle modlib (add_moduleinfo / update_moduleinfo).
 * Does not own academic data — writes only through Moodle APIs.
 *
 * Verified against Moodle 5.2.1 (`public/course/modlib.php`, `public/mod/assign/*`).
 * Signatures for add_moduleinfo / update_moduleinfo match 5.0 → 5.2; 5.2 adds CM AI
 * fields (enableaitools / enabledaiactions) applied inside set_moduleinfo_defaults().
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_espace\service;

defined('MOODLE_INTERNAL') || die();

use assign;
use grade_item;
use local_espace\helper\FileHelper;
use local_espace\helper\ModuleHelper;
use local_espace\output\ApiResponse;
use local_espace\permission\CapabilityChecker;
use local_espace\validator\AssignmentValidator;
use local_espace\validator\CourseValidator;
use local_espace\validator\ModuleValidator;
use local_espace\validator\SectionValidator;
use moodle_exception;
use stdClass;

/**
 * Assignment domain service.
 */
final class AssignmentService extends BaseService {

    /** @var AssignmentValidator */
    private AssignmentValidator $assignvalidator;

    /** @var ModuleValidator */
    private ModuleValidator $modulevalidator;

    /** @var SectionValidator */
    private SectionValidator $sectionvalidator;

    /** @var FileHelper */
    private FileHelper $files;

    public function __construct(
        ?CapabilityChecker $permissions = null,
        ?ModuleHelper $modules = null,
        ?CourseValidator $coursevalidator = null,
        ?AssignmentValidator $assignvalidator = null,
        ?ModuleValidator $modulevalidator = null,
        ?SectionValidator $sectionvalidator = null,
        ?FileHelper $files = null
    ) {
        parent::__construct($permissions, $modules, $coursevalidator);
        $this->assignvalidator = $assignvalidator ?? new AssignmentValidator();
        $this->modulevalidator = $modulevalidator ?? new ModuleValidator();
        $this->sectionvalidator = $sectionvalidator ?? new SectionValidator($this->modules, $this->coursevalidator);
        $this->files = $files ?? new FileHelper();
    }

    /**
     * Capability matrix for this subsystem.
     *
     * @return array
     */
    public function capability_matrix(): array {
        $this->permissions->require_login();
        $this->permissions->require_plugin_enabled();

        return ApiResponse::success('assignment_capability_matrix', [
            'use_official_ws' => [
                'mod_assign_get_assignments',
                'mod_assign_save_submission',
                'mod_assign_submit_for_grading',
                'mod_assign_save_grade',
                'mod_assign_get_submission_status',
                'core_files_get_unused_draft_itemid',
                'core_files_upload',
                'core_courseformat_update_course',
            ],
            'local_espace_gap' => 'local_espace_upsert_module (modname=assign)',
            'status' => 'sprint_a_upsert_ready',
        ]);
    }

    /**
     * Create or update an assignment activity via Moodle core APIs.
     *
     * @param int $courseid
     * @param int $sectionid Required for create (section id, not number). Ignored when updating.
     * @param int $cmid 0 to create; existing cmid to update.
     * @param array $settings Sprint A settings (validated).
     * @return array ApiResponse envelope
     * @throws moodle_exception
     */
    public function upsert(int $courseid, int $sectionid, int $cmid, array $settings): array {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $this->permissions->require_module_management($courseid);
        $course = $this->coursevalidator->require_course($courseid);

        // Moodle 5.2 set_moduleinfo_defaults() reads $PAGE->context for AI placements.
        // WS validate_context usually sets this; ensure course context before modlib writes.
        $PAGE->set_context(\context_course::instance($courseid));

        $iscreate = ($cmid <= 0);
        $validated = $this->assignvalidator->validate_settings($settings, $iscreate);

        if ($iscreate) {
            if ($sectionid <= 0) {
                throw new moodle_exception('errorrequiredfield', 'local_espace', '', 'sectionid');
            }
            [$course, $section] = $this->sectionvalidator->require_section($courseid, $sectionid);
            $moduleinfo = $this->build_create_moduleinfo($course, (int) $section->section, $validated);
            $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
            $operation = 'upsert_module_create';
            $cmid = (int) $moduleinfo->coursemodule;
        } else {
            $this->modulevalidator->require_cmid($cmid);
            [$cminfo, $modcontext, $cmcourse] = $this->permissions->require_module_context($cmid);
            if ((int) $cmcourse->id !== (int) $courseid) {
                throw new moodle_exception('errorcmcoursemismatch', 'local_espace');
            }
            if ($cminfo->modname !== 'assign') {
                throw new moodle_exception('errorcmnotassign', 'local_espace', '', $cminfo->modname);
            }

            $cmrecord = get_coursemodule_from_id('assign', $cmid, $courseid, false, MUST_EXIST);
            [$cm, $context, $module, $instance, $cw] = can_update_moduleinfo($cmrecord);
            $moduleinfo = $this->build_update_moduleinfo($course, $cm, $instance, $context, $validated);
            [$cm, $moduleinfo] = update_moduleinfo($cm, $moduleinfo, $course, null);
            $operation = 'upsert_module_update';
            $cmid = (int) $cm->id;
        }

        $this->modules->rebuild_course_cache($courseid);
        $cminfo = $this->modules->get_cm($cmid);

        return ApiResponse::success($operation, [
            'cm' => $this->modules->export_cm($cminfo),
            'modname' => 'assign',
            'courseid' => $courseid,
        ]);
    }

    /**
     * Build moduleinfo for create (Moodle add_moduleinfo).
     *
     * @param stdClass $course
     * @param int $sectionnum Relative section number
     * @param array $settings Validated settings
     * @return stdClass
     */
    private function build_create_moduleinfo(stdClass $course, int $sectionnum, array $settings): stdClass {
        // Moodle 5.2.1: prepare_new_moduleinfo_data() returns
        // array($module, $context, $cw, $cm, $data) — see course/modlib.php.
        // Core callers (modedit.php, format stateactions) use only $data as moduleinfo.
        [, , , , $data] = prepare_new_moduleinfo_data($course, 'assign', $sectionnum);
        $data->modulename = 'assign';
        $data->add = 'assign';
        $data->section = $sectionnum;

        $this->apply_assign_defaults($data);
        $this->apply_sprint_a_settings($data, $settings, true, null, null);
        $this->apply_default_feedback_plugins($data);

        return $data;
    }

    /**
     * Build moduleinfo for update (Moodle update_moduleinfo).
     *
     * @param stdClass $course
     * @param stdClass $cm course_modules record
     * @param stdClass $instance assign record
     * @param \context $context
     * @param array $settings Validated settings
     * @return stdClass
     */
    private function build_update_moduleinfo(
        stdClass $course,
        stdClass $cm,
        stdClass $instance,
        $context,
        array $settings
    ): stdClass {
        $moduleinfo = new stdClass();
        $moduleinfo->coursemodule = (int) $cm->id;
        $moduleinfo->cmidnumber = $cm->idnumber ?? '';
        $moduleinfo->instance = $instance->id;
        $moduleinfo->course = $course->id;
        $moduleinfo->modulename = 'assign';
        $moduleinfo->module = $cm->module;
        $moduleinfo->update = $cm->id;
        $moduleinfo->groupmode = $cm->groupmode;
        $moduleinfo->groupingid = $cm->groupingid;
        $moduleinfo->completion = $cm->completion;
        $moduleinfo->completionview = $cm->completionview;
        $moduleinfo->completionexpected = $cm->completionexpected;
        $moduleinfo->completiongradeitemnumber = $cm->completiongradeitemnumber;
        $moduleinfo->completionpassgrade = $cm->completionpassgrade ?? 0;
        $moduleinfo->showdescription = $cm->showdescription;
        $moduleinfo->visibleoncoursepage = $cm->visibleoncoursepage ?? 1;
        $moduleinfo->downloadcontent = $cm->downloadcontent ?? DOWNLOAD_COURSE_CONTENT_ENABLED;
        $moduleinfo->availability = $cm->availability;

        // Moodle 5.2+: preserve CM AI fields. set_moduleinfo_defaults() rebuilds
        // enabledaiactions from action-* form keys; without them, enabled actions become 0.
        $this->preserve_cm_ai_fields($moduleinfo, $cm);

        // Preserve non-Sprint-A assign fields from the existing instance.
        $moduleinfo->alwaysshowdescription = $instance->alwaysshowdescription;
        $moduleinfo->submissiondrafts = $instance->submissiondrafts;
        $moduleinfo->requiresubmissionstatement = $instance->requiresubmissionstatement;
        $moduleinfo->sendnotifications = $instance->sendnotifications;
        $moduleinfo->sendlatenotifications = $instance->sendlatenotifications;
        $moduleinfo->sendstudentnotifications = $instance->sendstudentnotifications;
        $moduleinfo->teamsubmission = $instance->teamsubmission;
        $moduleinfo->requireallteammemberssubmit = $instance->requireallteammemberssubmit;
        $moduleinfo->teamsubmissiongroupingid = $instance->teamsubmissiongroupingid;
        $moduleinfo->blindmarking = $instance->blindmarking;
        $moduleinfo->hidegrader = $instance->hidegrader;
        $moduleinfo->markingworkflow = $instance->markingworkflow;
        $moduleinfo->markingallocation = $instance->markingallocation;
        $moduleinfo->markinganonymous = $instance->markinganonymous ?? 0;
        $moduleinfo->attemptreopenmethod = $instance->attemptreopenmethod;
        $moduleinfo->maxattempts = $instance->maxattempts;
        $moduleinfo->preventsubmissionnotypes = $instance->preventsubmissionnotypes ?? 0;
        $moduleinfo->gradepenalty = $instance->gradepenalty ?? 0;
        $moduleinfo->timelimit = $instance->timelimit ?? 0;
        $moduleinfo->completionsubmit = $instance->completionsubmit ?? 0;

        // Moodle 5.2 assign multi-marking defaults (preserved when present on instance).
        if (property_exists($instance, 'markercount')) {
            $moduleinfo->markercount = $instance->markercount;
        }
        if (property_exists($instance, 'multimarkmethod')) {
            $moduleinfo->multimarkmethod = $instance->multimarkmethod;
        }
        if (property_exists($instance, 'multimarkrounding')) {
            $moduleinfo->multimarkrounding = $instance->multimarkrounding;
        }

        // Existing dates / grade as baselines (overridden by settings when present).
        $moduleinfo->allowsubmissionsfromdate = $instance->allowsubmissionsfromdate;
        $moduleinfo->duedate = $instance->duedate;
        $moduleinfo->cutoffdate = $instance->cutoffdate;
        $moduleinfo->gradingduedate = $instance->gradingduedate;
        $moduleinfo->grade = $instance->grade;
        $moduleinfo->name = $instance->name;
        $moduleinfo->intro = $instance->intro;
        $moduleinfo->introformat = $instance->introformat;
        $moduleinfo->visible = $cm->visible;

        $assignment = new assign($context, null, $course);
        $this->apply_existing_plugin_formdata($moduleinfo, $assignment);

        $this->apply_sprint_a_settings($moduleinfo, $settings, false, $context, $instance);

        // Grade category from existing grade item if not provided.
        if (!isset($moduleinfo->gradecat)) {
            $gi = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $instance->id,
                'itemnumber' => 0,
                'courseid' => $course->id,
            ]);
            if ($gi && $gi->categoryid) {
                $moduleinfo->gradecat = $gi->categoryid;
            }
        }

        return $moduleinfo;
    }

    /**
     * Apply Moodle admin defaults for fields outside Sprint A scope (create).
     *
     * @param stdClass $moduleinfo
     */
    private function apply_assign_defaults(stdClass $moduleinfo): void {
        $admin = get_config('assign');

        $moduleinfo->alwaysshowdescription = !empty($admin->alwaysshowdescription) ? 1 : 0;
        $moduleinfo->submissiondrafts = !empty($admin->submissiondrafts) ? 1 : 0;
        $moduleinfo->requiresubmissionstatement = !empty($admin->requiresubmissionstatement) ? 1 : 0;
        $moduleinfo->sendnotifications = !empty($admin->sendnotifications) ? 1 : 0;
        $moduleinfo->sendlatenotifications = !empty($admin->sendlatenotifications) ? 1 : 0;
        $moduleinfo->sendstudentnotifications = isset($admin->sendstudentnotifications)
            ? (int) $admin->sendstudentnotifications
            : 1;
        $moduleinfo->teamsubmission = 0;
        $moduleinfo->requireallteammemberssubmit = 0;
        $moduleinfo->teamsubmissiongroupingid = 0;
        $moduleinfo->blindmarking = 0;
        $moduleinfo->hidegrader = 0;
        $moduleinfo->markingworkflow = 0;
        $moduleinfo->markingallocation = 0;
        $moduleinfo->markinganonymous = 0;
        $moduleinfo->attemptreopenmethod = defined('ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS')
            ? ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS
            : 'untilpass';
        $moduleinfo->maxattempts = isset($admin->maxattempts) ? (int) $admin->maxattempts : 1;
        $moduleinfo->preventsubmissionnotypes = 0;
        $moduleinfo->gradepenalty = 0;
        $moduleinfo->timelimit = 0;
        $moduleinfo->completionsubmit = 0;

        $moduleinfo->allowsubmissionsfromdate = 0;
        $moduleinfo->duedate = 0;
        $moduleinfo->cutoffdate = 0;
        $moduleinfo->gradingduedate = 0;
        $moduleinfo->grade = 100;
    }

    /**
     * Enable common feedback plugins on create using plugin admin defaults.
     *
     * @param stdClass $moduleinfo
     */
    private function apply_default_feedback_plugins(stdClass $moduleinfo): void {
        foreach (\core_component::get_plugin_list('assignfeedback') as $type => $unused) {
            $enabledname = 'assignfeedback_' . $type . '_enabled';
            $default = get_config('assignfeedback_' . $type, 'default');
            $moduleinfo->$enabledname = ($default === false || $default === null) ? 0 : (int) !empty($default);
        }
        // Comments are the usual Moodle default when admin default is unset.
        if (!isset($moduleinfo->assignfeedback_comments_enabled)
                || $moduleinfo->assignfeedback_comments_enabled === 0) {
            // Leave as configured; do not force-enable if admin default is off.
        }
        if (!isset($moduleinfo->assignfeedback_comments_commentinline)) {
            $inline = get_config('assignfeedback_comments', 'inline');
            $moduleinfo->assignfeedback_comments_commentinline = !empty($inline) ? 1 : 0;
        }
    }

    /**
     * Copy existing submission/feedback plugin enabled + config into formdata for update.
     *
     * @param stdClass $moduleinfo
     * @param assign $assignment
     */
    private function apply_existing_plugin_formdata(stdClass $moduleinfo, assign $assignment): void {
        foreach ($assignment->get_submission_plugins() as $plugin) {
            if (!$plugin->is_visible()) {
                continue;
            }
            $enabledname = 'assignsubmission_' . $plugin->get_type() . '_enabled';
            $moduleinfo->$enabledname = $plugin->is_enabled() ? 1 : 0;

            if ($plugin->get_type() === 'file') {
                $moduleinfo->assignsubmission_file_maxfiles = (int) ($plugin->get_config('maxfilesubmissions') ?: 20);
                $moduleinfo->assignsubmission_file_maxsizebytes = (int) ($plugin->get_config('maxsubmissionsizebytes') ?: 0);
            }
            if ($plugin->get_type() === 'onlinetext') {
                $moduleinfo->assignsubmission_onlinetext_wordlimit = (int) ($plugin->get_config('wordlimit') ?: 0);
                $moduleinfo->assignsubmission_onlinetext_wordlimit_enabled =
                    !empty($plugin->get_config('wordlimitenabled')) ? 1 : 0;
            }
        }

        foreach ($assignment->get_feedback_plugins() as $plugin) {
            if (!$plugin->is_visible()) {
                continue;
            }
            $enabledname = 'assignfeedback_' . $plugin->get_type() . '_enabled';
            $moduleinfo->$enabledname = $plugin->is_enabled() ? 1 : 0;
            if ($plugin->get_type() === 'comments') {
                $moduleinfo->assignfeedback_comments_commentinline =
                    !empty($plugin->get_config('commentinline')) ? 1 : 0;
            }
        }
    }

    /**
     * Apply Sprint A authoring fields onto moduleinfo.
     *
     * @param stdClass $moduleinfo
     * @param array $settings
     * @param bool $iscreate
     * @param \context|null $context Module context (update) or null (create)
     * @param stdClass|null $instance Existing assign row (update)
     */
    private function apply_sprint_a_settings(
        stdClass $moduleinfo,
        array $settings,
        bool $iscreate,
        $context,
        ?stdClass $instance
    ): void {
        $moduleinfo->name = $settings['name'];

        $intro = $settings['intro'];
        $introformat = $settings['introformat'];

        $drafteditor = 0;
        if ($iscreate) {
            if (!empty($moduleinfo->introeditor['itemid'])) {
                $drafteditor = (int) $moduleinfo->introeditor['itemid'];
            } else {
                $drafteditor = file_get_unused_draft_itemid();
            }
        } else {
            $drafteditor = file_get_unused_draft_itemid();
            file_prepare_draft_area(
                $drafteditor,
                $context->id,
                'mod_assign',
                'intro',
                0,
                ['subdirs' => true]
            );
        }

        $moduleinfo->introeditor = [
            'text' => $intro,
            'format' => $introformat,
            'itemid' => $drafteditor,
        ];
        $moduleinfo->intro = $intro;
        $moduleinfo->introformat = $introformat;

        if (array_key_exists('introattachments', $settings) && $settings['introattachments'] !== null) {
            $moduleinfo->introattachments = (int) $settings['introattachments'];
        } else if (!$iscreate && $context) {
            // Preserve existing intro attachments by preparing a draft area.
            $attachdraft = file_get_unused_draft_itemid();
            file_prepare_draft_area(
                $attachdraft,
                $context->id,
                'mod_assign',
                'introattachment',
                0,
                ['subdirs' => true]
            );
            $moduleinfo->introattachments = $attachdraft;
        }

        foreach (['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'] as $datefield) {
            if (array_key_exists($datefield, $settings) && $settings[$datefield] !== null) {
                $moduleinfo->$datefield = (int) $settings[$datefield];
            }
        }

        if (array_key_exists('onlinetext_enabled', $settings)) {
            $moduleinfo->assignsubmission_onlinetext_enabled = (int) $settings['onlinetext_enabled'];
        } else if ($iscreate) {
            $moduleinfo->assignsubmission_onlinetext_enabled = 1;
        }

        if (array_key_exists('file_enabled', $settings)) {
            $moduleinfo->assignsubmission_file_enabled = (int) $settings['file_enabled'];
        } else if ($iscreate) {
            $moduleinfo->assignsubmission_file_enabled = 1;
        }

        if (!empty($moduleinfo->assignsubmission_file_enabled)) {
            if (array_key_exists('maxfiles', $settings) && $settings['maxfiles'] !== null) {
                $moduleinfo->assignsubmission_file_maxfiles = (int) $settings['maxfiles'];
            } else if (!isset($moduleinfo->assignsubmission_file_maxfiles)) {
                $moduleinfo->assignsubmission_file_maxfiles = (int) (get_config('assignsubmission_file', 'maxfiles') ?: 20);
            }
            if (array_key_exists('maxsizebytes', $settings) && $settings['maxsizebytes'] !== null) {
                $moduleinfo->assignsubmission_file_maxsizebytes = (int) $settings['maxsizebytes'];
            } else if (!isset($moduleinfo->assignsubmission_file_maxsizebytes)) {
                $moduleinfo->assignsubmission_file_maxsizebytes =
                    (int) (get_config('assignsubmission_file', 'maxbytes') ?: 0);
            }
        }

        if (!isset($moduleinfo->assignsubmission_onlinetext_wordlimit)) {
            $moduleinfo->assignsubmission_onlinetext_wordlimit = 0;
        }
        if (!isset($moduleinfo->assignsubmission_onlinetext_wordlimit_enabled)) {
            $moduleinfo->assignsubmission_onlinetext_wordlimit_enabled = 0;
        }

        // Disable other visible submission plugins not managed in Sprint A unless already set on update.
        foreach (\core_component::get_plugin_list('assignsubmission') as $type => $unused) {
            if (in_array($type, ['onlinetext', 'file', 'comments'], true)) {
                continue;
            }
            $enabledname = 'assignsubmission_' . $type . '_enabled';
            if ($iscreate || !isset($moduleinfo->$enabledname)) {
                $moduleinfo->$enabledname = 0;
            }
        }
        // Submission comments: preserve on update; default off on create (optional plugin).
        if ($iscreate && !isset($moduleinfo->assignsubmission_comments_enabled)) {
            $default = get_config('assignsubmission_comments', 'default');
            $moduleinfo->assignsubmission_comments_enabled = !empty($default) ? 1 : 0;
        }

        $this->apply_grade_settings($moduleinfo, $settings, $iscreate, $instance);

        if (array_key_exists('visible', $settings)) {
            $moduleinfo->visible = (int) $settings['visible'];
        } else if ($iscreate) {
            $moduleinfo->visible = 1;
        }
        $moduleinfo->visibleoncoursepage = $moduleinfo->visibleoncoursepage ?? 1;
    }

    /**
     * Map grade_type / grade / scaleid / gradecat to Moodle assign grade field.
     *
     * @param stdClass $moduleinfo
     * @param array $settings
     * @param bool $iscreate
     * @param stdClass|null $instance
     * @throws moodle_exception
     */
    private function apply_grade_settings(
        stdClass $moduleinfo,
        array $settings,
        bool $iscreate,
        ?stdClass $instance
    ): void {
        $gradetype = $settings['grade_type'] ?? null;

        if ($gradetype === null && !$iscreate) {
            // Preserve existing grade; still allow gradecat update.
            if (array_key_exists('gradecat', $settings) && $settings['gradecat'] !== null) {
                $moduleinfo->gradecat = (int) $settings['gradecat'];
            }
            return;
        }

        if ($gradetype === null) {
            $gradetype = 'point';
        }

        switch ($gradetype) {
            case 'none':
                $moduleinfo->grade = 0;
                break;
            case 'scale':
                $scaleid = (int) ($settings['scaleid'] ?? 0);
                if ($scaleid <= 0) {
                    throw new moodle_exception('errorrequiredfield', 'local_espace', '', 'scaleid');
                }
                $moduleinfo->grade = -1 * $scaleid;
                break;
            case 'point':
            default:
                $max = array_key_exists('grade', $settings) ? (float) $settings['grade'] : 100.0;
                $moduleinfo->grade = $max;
                break;
        }

        if (array_key_exists('gradecat', $settings) && $settings['gradecat'] !== null) {
            $moduleinfo->gradecat = (int) $settings['gradecat'];
        }
    }

    /**
     * Preserve Moodle 5.2 course_modules AI fields across update_moduleinfo.
     *
     * Moodle's set_moduleinfo_defaults() rebuilds enabledaiactions from action-* keys.
     * Rehydrate those keys from the existing CM JSON so we do not clear AI settings.
     *
     * @param stdClass $moduleinfo
     * @param stdClass $cm
     */
    private function preserve_cm_ai_fields(stdClass $moduleinfo, stdClass $cm): void {
        if (property_exists($cm, 'enableaitools')) {
            $moduleinfo->enableaitools = $cm->enableaitools;
        }

        if (empty($cm->enabledaiactions)) {
            return;
        }

        $actions = json_decode((string) $cm->enabledaiactions, true);
        if (!is_array($actions)) {
            // Keep raw value; set_moduleinfo_defaults may still rebuild from empty action keys.
            $moduleinfo->enabledaiactions = $cm->enabledaiactions;
            return;
        }

        foreach ($actions as $action => $enabled) {
            $actionname = 'action-' . $action;
            $moduleinfo->{$actionname} = !empty($enabled) ? 1 : 0;
        }
    }
}
