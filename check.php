<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The teacher's Check / Re-check button: reads the submission's text on the server and starts a scan.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use plagiarism_pcx\checker;

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$item = required_param('item', PARAM_ALPHANUMEXT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('plagiarism/pcx:check', $context);

$PAGE->set_url(new moodle_url('/plagiarism/pcx/check.php', ['cmid' => $cmid]));
$back = $returnurl !== '' ? new moodle_url($returnurl)
    : new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']);

if (!checker::enabled_for($cm->id)) {
    redirect($back, get_string('error_disabled', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_ERROR);
}

if ($item === checker::TEXT_KEY) {
    $text = checker::online_text($cm, $userid);
    if ($text === null) {
        throw new moodle_exception('error_nosubmission', 'plagiarism_pcx');
    }
    $identifier = sha1($text);
} else {
    $file = get_file_storage()->get_file_by_hash($item);
    // Only a file submitted to this assignment by this user.
    if (
        !$file || (int) $file->get_contextid() !== (int) $context->id || $file->get_component() !== 'assignsubmission_file'
            || (int) $file->get_userid() !== $userid || $file->is_directory()
    ) {
        throw new moodle_exception('error_nosubmission', 'plagiarism_pcx');
    }
    if (!checker::supported($file)) {
        redirect($back, get_string('unsupported', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $text = checker::text_from_file($file);
    $identifier = $file->get_contenthash();
}

$error = checker::start($cm, $userid, $item, $text, $identifier);
if ($error !== '') {
    redirect($back, $error, null, \core\output\notification::NOTIFY_ERROR);
}
redirect($back, get_string('started', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_SUCCESS);
