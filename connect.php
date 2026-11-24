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
 * Connect: starts OAuth (POST with sesskey from the settings page) and receives the return.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use plagiarism_pcx\oauth;

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
$PAGE->set_context(context_system::instance());
$PAGE->set_url(oauth::redirect_uri());

$settings = new moodle_url('/plagiarism/pcx/settings.php');

if (optional_param('start', 0, PARAM_BOOL)) {
    require_sesskey();
    $url = oauth::start();
    if (!$url) {
        redirect(
            $settings,
            get_string('connect_noregister', 'plagiarism_pcx'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    redirect($url);
}

// The return step: `state` is the check.
$error = oauth::finish(
    optional_param('state', '', PARAM_RAW_TRIMMED),
    optional_param('code', '', PARAM_RAW_TRIMMED),
    optional_param('error', '', PARAM_ALPHAEXT)
);
if ($error !== '') {
    redirect($settings, $error, null, \core\output\notification::NOTIFY_ERROR);
}
redirect($settings, get_string('connected', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_SUCCESS);
