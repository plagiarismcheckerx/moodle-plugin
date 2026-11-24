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
 * Site administration → Plugins → Plagiarism → Plagiarism Checker X: switch it on, connect the
 * site (Connect, or an API key), see the account's words left, disconnect.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use plagiarism_pcx\api;
use plagiarism_pcx\brand;
use plagiarism_pcx\connection;
use plagiarism_pcx\oauth;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('plagiarismpcx');
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$self = new moodle_url('/plagiarism/pcx/settings.php');
$action = optional_param('action', '', PARAM_ALPHA);

if ($action !== '') {
    require_sesskey();
    switch ($action) {
        case 'save':
            set_config('enabled', optional_param('enabled', 0, PARAM_BOOL) ? 1 : 0, 'plagiarism_pcx');
            set_config('defaulton', optional_param('defaulton', 0, PARAM_BOOL) ? 1 : 0, 'plagiarism_pcx');
            set_config('disclosure', optional_param('disclosure', '', PARAM_TEXT), 'plagiarism_pcx');
            redirect($self, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'key':
            $key = trim(optional_param('apikey', '', PARAM_RAW_TRIMMED));
            if ($key === '' || preg_match('~[^\x21-\x7e]~', $key)) {
                redirect($self, get_string('key_invalid', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_ERROR);
            }
            // Test the key before saving it.
            $before = connection::get(true);
            connection::save(['api_key' => $key] + array_intersect_key($before, ['client_id' => 1, 'client_for' => 1]));
            $res = api::request('GET', '/account');
            if ($res['status'] !== 200) {
                connection::save($before);
                redirect($self, api::message($res), null, \core\output\notification::NOTIFY_ERROR);
            }
            if (connection::mode() === 'connect') {
                oauth::disconnect();
            }
            redirect($self, get_string('connected', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'disconnect':
            if (connection::mode() === 'connect') {
                oauth::disconnect();
            } else {
                $c = connection::get(true);
                connection::save(array_intersect_key($c, ['client_id' => 1, 'client_for' => 1]));
            }
            redirect($self, get_string('disconnected', 'plagiarism_pcx'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'plagiarism_pcx'));

if (empty($CFG->enableplagiarism)) {
    echo $OUTPUT->notification(get_string(
        'plagiarismoff',
        'plagiarism_pcx',
        (new moodle_url('/admin/search.php', ['query' => 'enableplagiarism']))->out()
    ), 'warning');
}

// Connection.
echo html_writer::start_div('plagiarism-pcx-settings');
echo $OUTPUT->heading(get_string('connection', 'plagiarism_pcx'), 3);
$mode = connection::mode();
if ($mode === '') {
    echo html_writer::tag('p', get_string('connect_intro', 'plagiarism_pcx', brand::NAME));
    echo $OUTPUT->single_button(
        new moodle_url('/plagiarism/pcx/connect.php', ['start' => 1]),
        get_string('connect', 'plagiarism_pcx', brand::NAME),
        'post',
        ['primary' => true]
    );

    echo html_writer::tag('p', get_string('key_intro', 'plagiarism_pcx', (object) [
        'url' => brand::app_url(),
        'name' => brand::NAME,
    ]), ['class' => 'mt-3']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $self->out(false), 'class' => 'form-inline']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'key']);
    echo html_writer::label(get_string('apikey', 'plagiarism_pcx'), 'pcx-apikey', false, ['class' => 'sr-only']);
    echo html_writer::empty_tag('input', ['type' => 'password', 'name' => 'apikey', 'id' => 'pcx-apikey',
        'class' => 'form-control mr-2', 'placeholder' => brand::KEY_HINT, 'autocomplete' => 'off', 'size' => 40]);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary',
        'value' => get_string('savekey', 'plagiarism_pcx')]);
    echo html_writer::end_tag('form');
} else {
    echo html_writer::tag('p', get_string($mode === 'key' ? 'connected_key' : 'connected_oauth', 'plagiarism_pcx', brand::NAME));
    $account = api::request('GET', '/account');
    if ($account['status'] === 200) {
        $left = (array) ($account['body']['words_left'] ?? []);
        $words = function ($n) {
            return $n === null ? '—' : get_string('wordsleft', 'plagiarism_pcx', format_float((int) $n, 0));
        };
        $table = new html_table();
        $table->attributes['class'] = 'generaltable plagiarism-pcx-account';
        $table->data = [
            [get_string('plan', 'plagiarism_pcx'), s((string) ($account['body']['plan']['name'] ?? '—'))],
            [get_string('module_plagiarism', 'plagiarism_pcx'), $words($left['scan'] ?? null)],
            [get_string('module_ai_detection', 'plagiarism_pcx'), $words($left['ai_detection'] ?? null)],
            [get_string('extrawords', 'plagiarism_pcx'), $words($left['extra'] ?? 0)],
        ];
        echo html_writer::table($table);
        if (isset($account['body']['plan']['active']) && !$account['body']['plan']['active']) {
            echo $OUTPUT->notification(get_string('planended', 'plagiarism_pcx'), 'warning');
        }
    } else {
        echo $OUTPUT->notification(s(api::message($account)), 'error');
    }
    echo $OUTPUT->single_button(
        new moodle_url($self, ['action' => 'disconnect']),
        get_string('disconnect', 'plagiarism_pcx'),
        'post'
    );
}

// Options.
echo $OUTPUT->heading(get_string('options', 'plagiarism_pcx'), 3, 'mt-4');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $self->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
foreach (['enabled', 'defaulton'] as $name) {
    echo html_writer::start_div('form-check mb-2');
    echo html_writer::checkbox(
        $name,
        1,
        (bool) get_config('plagiarism_pcx', $name),
        ' ' . get_string($name, 'plagiarism_pcx'),
        ['id' => 'pcx-' . $name, 'class' => 'form-check-input']
    );
    echo html_writer::end_div();
}
echo html_writer::label(get_string('disclosure', 'plagiarism_pcx'), 'pcx-disclosure', true, ['class' => 'mt-2']);
echo html_writer::tag('textarea', s((string) get_config('plagiarism_pcx', 'disclosure')), [
    'name' => 'disclosure', 'id' => 'pcx-disclosure', 'rows' => 3, 'class' => 'form-control',
    'placeholder' => get_string('disclosure_default', 'plagiarism_pcx'),
]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary mt-3',
    'value' => get_string('savechanges')]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $OUTPUT->footer();
