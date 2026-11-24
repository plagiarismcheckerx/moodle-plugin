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
 * Plagiarism Checker X: the plagiarism API hooks.
 *
 * Nothing is checked automatically: a teacher clicks Check next to a submission, which spends
 * words from the connected account.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use plagiarism_pcx\checker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');

/**
 * Plagiarism Checker X plugin.
 */
class plagiarism_plugin_pcx extends plagiarism_plugin {
    /** @var int Pending checks refreshed inline per page, so a long grading table stays fast. */
    public const INLINE_REFRESHES = 10;

    /** @var int Inline refreshes done on this page. */
    private static $refreshed = 0;

    /**
     * The status, score and buttons shown next to a submission.
     *
     * @param array $linkarray cmid, userid, and content (online text) or file (stored_file).
     * @return string HTML.
     */
    public function get_links($linkarray) {
        global $OUTPUT;
        $cmid = (int) ($linkarray['cmid'] ?? 0);
        $userid = (int) ($linkarray['userid'] ?? 0);
        if (!$cmid || !$userid || !checker::enabled_for($cmid)) {
            return '';
        }
        $context = context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('plagiarism/pcx:viewreport', $context)) {
            return '';
        }

        if (!empty($linkarray['file']) && $linkarray['file'] instanceof stored_file) {
            $file = $linkarray['file'];
            if (!checker::supported($file)) {
                return $this->wrap(html_writer::span(get_string('unsupported', 'plagiarism_pcx'), 'pcx-muted'));
            }
            $itemkey = $file->get_pathnamehash();
            $identifier = $file->get_contenthash();
        } else if (isset($linkarray['content'])) {
            $text = checker::text_from_html((string) $linkarray['content']);
            if ($text === '') {
                return '';
            }
            $itemkey = checker::TEXT_KEY;
            $identifier = sha1($text);
        } else {
            return '';
        }

        $row = checker::get($cmid, $userid, $itemkey);
        if (
            $row && $row->status === 'pending' && $row->timemodified < time() - 15
                && self::$refreshed < self::INLINE_REFRESHES
        ) {
            self::$refreshed++;
            $row = checker::refresh($row);
        }

        $cancheck = has_capability('plagiarism/pcx:check', $context);
        $button = function (string $label) use ($cmid, $userid, $itemkey, $OUTPUT): string {
            $url = new moodle_url('/plagiarism/pcx/check.php', [
                'cmid' => $cmid,
                'userid' => $userid,
                'item' => $itemkey,
                'returnurl' => qualified_me() ?: '',
            ]);
            return $OUTPUT->single_button($url, $label, 'post', ['class' => 'pcx-btn']);
        };

        if (!$row) {
            return $this->wrap($cancheck ? $button(get_string('check', 'plagiarism_pcx')) : '');
        }
        if ($row->status === 'pending') {
            return $this->wrap(html_writer::span(get_string('checking', 'plagiarism_pcx'), 'pcx-badge pcx-pending'));
        }
        if ($row->status === 'failed') {
            $out = html_writer::span(s($row->error ?: get_string('error_server', 'plagiarism_pcx')), 'pcx-error');
            return $this->wrap($out . ($cancheck ? $button(get_string('tryagain', 'plagiarism_pcx')) : ''));
        }

        $out = $this->badges(json_decode((string) $row->results, true) ?: []);
        if (!empty($row->reporturl)) {
            $out .= html_writer::link(
                $row->reporturl,
                get_string('report', 'plagiarism_pcx') . ' ↗',
                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'pcx-link',
                'title' => get_string(
                    'openreport',
                    'plagiarism_pcx'
                )]
            );
        }
        if ($row->identifier !== $identifier) {
            $out .= html_writer::span(get_string('changed', 'plagiarism_pcx'), 'pcx-muted');
            if ($cancheck) {
                $out .= $button(get_string('recheck', 'plagiarism_pcx'));
            }
        }
        return $this->wrap($out);
    }

    /**
     * One small badge per check.
     *
     * @param array $results
     * @return string
     */
    private function badges(array $results): string {
        $out = '';
        foreach (checker::MODULES as $module) {
            if (!isset($results[$module])) {
                continue;
            }
            $r = $results[$module];
            $name = get_string('short_' . $module, 'plagiarism_pcx');
            if (!empty($r['failed'])) {
                $out .= html_writer::span(
                    $name . ' ' . html_writer::tag('b', '—'),
                    'pcx-stat pcx-neutral',
                    ['title' => $r['message'] ?? '']
                );
                continue;
            }
            switch ($module) {
                case 'plagiarism':
                    $value = self::percent((float) $r['similarity']);
                    $tone = $r['similarity'] >= 25 ? 'pcx-bad' : ($r['similarity'] >= 10 ? 'pcx-warn' : 'pcx-ok');
                    break;
                case 'ai_detection':
                    if (isset($r['refusal'])) {
                        $value = '—';
                        $tone = 'pcx-neutral';
                        break;
                    }
                    $value = $r['score'] === null ? '—' : $r['score'] . '%';
                    $tone = $r['band'] === 'elevated' ? 'pcx-bad' : ($r['band'] === 'moderate' ? 'pcx-warn' : 'pcx-ok');
                    break;
                case 'readability':
                    $value = $r['ease'] === null ? '—' : (string) $r['ease'];
                    $tone = 'pcx-neutral';
                    break;
                default:
                    $value = get_string('issues', 'plagiarism_pcx', $r['issues']);
                    $tone = 'pcx-neutral';
            }
            $out .= html_writer::span(
                $name . ' ' . html_writer::tag('b', s($value)),
                'pcx-stat ' . $tone,
                ['title' => $r['refusal'] ?? get_string('module_' . $module, 'plagiarism_pcx')]
            );
        }
        return $out;
    }

    /**
     * "6%", or "0.4%" under 1% so a real match never reads as 0% (the app's rule).
     *
     * @param float $share
     * @return string
     */
    private static function percent(float $share): string {
        if ($share > 0 && $share < 1) {
            return (round($share * 10) / 10) . '%';
        }
        return round($share) . '%';
    }

    /**
     * Wrap the links in the plugin's box.
     *
     * @param string $html
     * @return string
     */
    private function wrap(string $html): string {
        global $OUTPUT;
        if ($html === '') {
            return '';
        }
        $name = get_string('pluginname', 'plagiarism_pcx');
        $logo = html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('logo', 'plagiarism_pcx'),
            'alt' => $name, 'title' => $name, 'class' => 'pcx-logo', 'width' => 16, 'height' => 16]);
        return html_writer::div($logo . $html, 'plagiarism-pcx');
    }

    /**
     * Tell students on the submission page that their work may be checked.
     *
     * @param int $cmid
     * @return string
     */
    public function print_disclosure($cmid) {
        global $OUTPUT;
        if (!checker::enabled_for((int) $cmid)) {
            return '';
        }
        $text = get_config('plagiarism_pcx', 'disclosure') ?: get_string('disclosure_default', 'plagiarism_pcx');
        return $OUTPUT->box(format_text($text, FORMAT_MOODLE), 'generalbox plagiarism-pcx-disclosure');
    }
}

/**
 * Add the plugin's settings to the assignment settings form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function plagiarism_pcx_coursemodule_standard_elements($formwrapper, $mform) {
    global $CFG;
    if (empty($CFG->enableplagiarism) || !get_config('plagiarism_pcx', 'enabled')) {
        return;
    }
    $current = $formwrapper->get_current();
    if (($current->modulename ?? '') !== 'assign') {
        return;
    }
    $context = $formwrapper->get_context();
    if (!has_capability('plagiarism/pcx:enable', $context)) {
        return;
    }

    $cmid = (int) ($current->coursemodule ?? 0);
    $settings = $cmid ? checker::settings($cmid)
        : ['enabled' => (bool) get_config('plagiarism_pcx', 'defaulton'), 'modules' => checker::DEFAULT_MODULES];

    $mform->addElement('header', 'plagiarism_pcx_header', get_string('pluginname', 'plagiarism_pcx'));
    $mform->addElement('advcheckbox', 'plagiarism_pcx_enabled', get_string('useforactivity', 'plagiarism_pcx'));
    $mform->addHelpButton('plagiarism_pcx_enabled', 'useforactivity', 'plagiarism_pcx');
    $mform->setDefault('plagiarism_pcx_enabled', $settings['enabled'] ? 1 : 0);

    $boxes = [];
    foreach (checker::MODULES as $module) {
        $boxes[] = $mform->createElement('advcheckbox', $module, '', get_string('module_' . $module, 'plagiarism_pcx'));
    }
    $mform->addGroup($boxes, 'plagiarism_pcx_modules', get_string('checks', 'plagiarism_pcx'), ' ', true);
    foreach (checker::MODULES as $module) {
        $mform->setDefault('plagiarism_pcx_modules[' . $module . ']', in_array($module, $settings['modules'], true) ? 1 : 0);
    }
    $mform->hideIf('plagiarism_pcx_modules', 'plagiarism_pcx_enabled', 'notchecked');
}

/**
 * Save the plugin's settings when the assignment is saved.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function plagiarism_pcx_coursemodule_edit_post_actions($data, $course) {
    if (!isset($data->plagiarism_pcx_enabled) || ($data->modulename ?? '') !== 'assign') {
        return $data;
    }
    $context = context_module::instance($data->coursemodule);
    if (!has_capability('plagiarism/pcx:enable', $context)) {
        return $data;
    }
    $modules = [];
    foreach ((array) ($data->plagiarism_pcx_modules ?? []) as $module => $on) {
        if ($on) {
            $modules[] = $module;
        }
    }
    checker::save_settings((int) $data->coursemodule, !empty($data->plagiarism_pcx_enabled), $modules);
    return $data;
}

/**
 * Forget an activity's checks when it is deleted.
 *
 * @param stdClass $cm
 */
function plagiarism_pcx_pre_course_module_delete($cm) {
    global $DB;
    $DB->delete_records('plagiarism_pcx_files', ['cm' => $cm->id]);
    $DB->delete_records('plagiarism_pcx_config', ['cm' => $cm->id]);
}
