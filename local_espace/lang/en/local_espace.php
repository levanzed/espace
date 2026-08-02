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
 * English language strings for local_espace.
 *
 * @package    local_espace
 * @copyright  2026 LevanzEd / ESPACE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ESPACE';
$string['espace:use'] = 'Use ESPACE local web services';
$string['espace:managecourse'] = 'Manage courses via ESPACE';
$string['espace:managesections'] = 'Manage course sections via ESPACE';
$string['espace:managemodules'] = 'Manage course modules via ESPACE';
$string['espace:manageavailability'] = 'Manage availability via ESPACE';
$string['espace:managecompletion'] = 'Manage completion settings via ESPACE';
$string['espace:viewstatus'] = 'View ESPACE status and capability matrices';

$string['settingenabled'] = 'Enable ESPACE local services';
$string['settingenabled_desc'] = 'When disabled, all local_espace web service calls fail closed.';
$string['settingstrictpermissions'] = 'Strict permission mode';
$string['settingstrictpermissions_desc'] = 'When enabled, every listed capability must be present. When disabled, any listed capability is sufficient.';
$string['settingarchitectureheading'] = 'Architecture';
$string['settingarchitectureheading_desc'] = 'local_espace only exposes capabilities that official Moodle Web Services do not cover adequately. Prefer core_* and mod_* functions wherever they exist.';

$string['privacy:metadata'] = 'The ESPACE local plugin does not store personal data. It proxies authorised course structure operations into Moodle core APIs.';

$string['eventsectioncreated'] = 'ESPACE section created';
$string['eventsectionupdated'] = 'ESPACE section updated';
$string['eventsectionmoved'] = 'ESPACE section moved';
$string['eventsectionvisibilitychanged'] = 'ESPACE section visibility changed';
$string['eventsectiondeleted'] = 'ESPACE section deleted';

$string['errorplugindisabled'] = 'The ESPACE local plugin is disabled by an administrator.';
$string['errornocapabilities'] = 'No capabilities were supplied to the permission checker.';
$string['errorinvalidcourseid'] = 'Invalid course id: {$a}';
$string['errorinvalidsectionid'] = 'Invalid section id: {$a}';
$string['errorinvalidcmid'] = 'Invalid course module id: {$a}';
$string['errorinvalidmodname'] = 'Invalid or unsupported module name: {$a}';
$string['errormodulenotinstalled'] = 'Module plugin is not installed on this site: {$a}';
$string['errorrequiredfield'] = 'Required field missing or empty: {$a}';
$string['errorfieldtoolong'] = 'Field exceeds maximum length: {$a}';
$string['errorsectioncoursemismatch'] = 'The section does not belong to the specified course.';
$string['errorsectionrenamenodata'] = 'Provide at least a name or a summary to rename a section.';
$string['errorinvalidsummaryformat'] = 'Invalid summary format.';
$string['errorcannotmovegeneralsection'] = 'The general section (section 0) cannot be moved.';
$string['errorcannotdeletegeneralsection'] = 'The general section (section 0) cannot be deleted.';
$string['errorcannotdeletesection'] = 'You cannot delete this section (format or capability restriction).';
$string['errorsectionnotempty'] = 'Section is not empty. Pass forcedeleteifnotempty to delete it with its contents.';
$string['errorinvalidmovedestination'] = 'Invalid move destination: {$a}';
$string['errorinvalidsectionposition'] = 'Invalid section create position: {$a}';
$string['errorinvalidvisibility'] = 'Visibility must be 0 or 1 (got {$a}).';
$string['errormaxsections'] = 'Course has reached the maximum number of sections ({$a}).';
$string['errormovesectionfailed'] = 'Moving the section failed.';
$string['errordeletesectionfailed'] = 'Deleting the section failed.';
$string['errorfilenotfound'] = 'File not found: {$a}';
$string['errormoduleupsertunsupported'] = 'Module upsert is not implemented for this activity type yet: {$a}';
$string['errorcmcoursemismatch'] = 'The course module does not belong to the specified course.';
$string['errorcmnotassign'] = 'Expected an assignment course module, got: {$a}';
$string['errorinvalidgradetype'] = 'Invalid grade type (use none, point, or scale): {$a}';
$string['errorinvalidgrade'] = 'Grade maximum must be zero or a positive number.';
$string['errorinvalidinteger'] = 'Invalid integer value for field: {$a}';
$string['errordateorder'] = 'Invalid assignment date order: {$a}';
$string['errorquizpublishnoquestions'] = 'Quiz publish requires at least one question.';
$string['errorquizpublishunsupportedqtype'] = 'Unsupported question type: {$a}';
$string['errorquizpublishmcqchoices'] = 'Multiple choice questions need at least two choices and exactly one correct answer.';
$string['errorquizpublishshortanswers'] = 'Short answer questions need at least one accepted answer.';
$string['errorquizpublishnobank'] = 'Could not resolve the course question bank for publishing.';
$string['errorquizpublishinvalidquestion'] = 'Invalid question at index {$a}.';
$string['errorquizpublishemptystem'] = 'Question stem must not be empty (index {$a}).';
$string['errorquizpublishinvalidmark'] = 'Question mark must be greater than zero (index {$a}).';
