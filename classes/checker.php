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

namespace plagiarism_pcx;

/**
 * Checks on submissions: what text is sent, starting a scan, and reading its result back.
 * One row per submission item (the online text, or one file) in plagiarism_pcx_files; a re-check
 * sends previous_scan_id, so the account is charged only for the edited words.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checker {
    /** @var string[] Checks the API runs. */
    public const MODULES = ['plagiarism', 'ai_detection', 'grammar', 'readability'];

    /** @var string[] Modules on by default for a new assignment. */
    public const DEFAULT_MODULES = ['plagiarism', 'ai_detection'];

    /** @var string[] File extensions text can be read from. */
    public const EXTENSIONS = ['txt', 'md', 'html', 'htm', 'docx', 'odt'];

    /** @var int Largest file read, in bytes. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** @var string Item key of an online text answer. */
    public const TEXT_KEY = 'onlinetext';

    /**
     * Per-activity settings, with defaults.
     *
     * @param int $cmid
     * @return array{enabled:bool, modules:string[]}
     */
    public static function settings(int $cmid): array {
        global $DB;
        $rows = $DB->get_records_menu('plagiarism_pcx_config', ['cm' => $cmid], '', 'name, value');
        $modules = isset($rows['modules']) ? array_values(array_intersect(explode(',', $rows['modules']), self::MODULES))
            : self::DEFAULT_MODULES;
        return ['enabled' => !empty($rows['enabled']), 'modules' => $modules];
    }

    /**
     * Save per-activity settings.
     *
     * @param int $cmid
     * @param bool $enabled
     * @param string[] $modules
     */
    public static function save_settings(int $cmid, bool $enabled, array $modules): void {
        global $DB;
        $values = [
            'enabled' => $enabled ? '1' : '0',
            'modules' => implode(',', array_values(array_intersect(self::MODULES, $modules))),
        ];
        foreach ($values as $name => $value) {
            $id = $DB->get_field('plagiarism_pcx_config', 'id', ['cm' => $cmid, 'name' => $name]);
            if ($id) {
                $DB->set_field('plagiarism_pcx_config', 'value', $value, ['id' => $id]);
            } else {
                $DB->insert_record('plagiarism_pcx_config', (object) ['cm' => $cmid, 'name' => $name, 'value' => $value]);
            }
        }
    }

    /**
     * Whether the plugin is on for this site and this activity.
     *
     * @param int $cmid
     * @return bool
     */
    public static function enabled_for(int $cmid): bool {
        global $CFG;
        return !empty($CFG->enableplagiarism) && get_config('plagiarism_pcx', 'enabled')
            && self::settings($cmid)['enabled'];
    }

    /**
     * Plain text of an online text answer.
     *
     * @param string $html
     * @return string
     */
    public static function text_from_html(string $html): string {
        return trim(html_to_text($html, 0, false));
    }

    /**
     * The online text of a user's latest submission in an assignment.
     *
     * @param \cm_info|\stdClass $cm
     * @param int $userid
     * @return string|null Null when there is none.
     */
    public static function online_text($cm, int $userid): ?string {
        global $DB;
        $sql = "SELECT ot.onlinetext
                  FROM {assignsubmission_onlinetext} ot
                  JOIN {assign_submission} s ON s.id = ot.submission
                 WHERE s.assignment = :assignment AND s.userid = :userid AND s.latest = 1";
        $html = $DB->get_field_sql($sql, ['assignment' => $cm->instance, 'userid' => $userid]);
        return $html === false ? null : self::text_from_html((string) $html);
    }

    /**
     * Whether text can be read from this file.
     *
     * @param \stored_file $file
     * @return bool
     */
    public static function supported(\stored_file $file): bool {
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        return in_array($ext, self::EXTENSIONS, true) && $file->get_filesize() <= self::MAX_BYTES;
    }

    /**
     * Plain text of a submitted file.
     *
     * @param \stored_file $file
     * @return string
     */
    public static function text_from_file(\stored_file $file): string {
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        switch ($ext) {
            case 'txt':
            case 'md':
                return trim(\core_text::convert($file->get_content(), self::charset($file->get_content()), 'utf-8'));
            case 'html':
            case 'htm':
                return self::text_from_html($file->get_content());
            case 'docx':
                return self::text_from_zip($file, 'word/document.xml', 'w');
            case 'odt':
                return self::text_from_zip($file, 'content.xml', 'text');
        }
        return '';
    }

    /**
     * Guess a plain text file's charset.
     *
     * @param string $bytes
     * @return string
     */
    private static function charset(string $bytes): string {
        return mb_check_encoding($bytes, 'UTF-8') ? 'utf-8' : 'windows-1252';
    }

    /**
     * Text from the body XML of a .docx (ns 'w') or .odt (ns 'text').
     *
     * @param \stored_file $file
     * @param string $entry
     * @param string $ns
     * @return string
     */
    private static function text_from_zip(\stored_file $file, string $entry, string $ns): string {
        $path = $file->copy_content_to_temp('plagiarism_pcx');
        if (!$path) {
            return '';
        }
        $zip = new \ZipArchive();
        $xml = '';
        if ($zip->open($path) === true) {
            $xml = (string) $zip->getFromName($entry);
            $zip->close();
        }
        @unlink($path);
        if ($xml === '') {
            return '';
        }
        if ($ns === 'w') {
            $xml = preg_replace(['~</w:p>~', '~<w:tab/>~', '~<w:br[^>]*/>~'], ["\n\n", "\t", "\n"], $xml);
        } else {
            $xml = preg_replace(
                ['~</text:(p|h)>~', '~<text:tab/>~', '~<text:line-break/>~', '~<text:s/>~'],
                ["\n\n", "\t", "\n", ' '],
                $xml
            );
        }
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim(preg_replace("~\n{3,}~", "\n\n", $text));
    }

    /**
     * The saved check for one submission item, if any.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $itemkey
     * @return \stdClass|null
     */
    public static function get(int $cmid, int $userid, string $itemkey): ?\stdClass {
        global $DB;
        $row = $DB->get_record('plagiarism_pcx_files', ['cm' => $cmid, 'userid' => $userid, 'itemkey' => $itemkey]);
        return $row ?: null;
    }

    /**
     * Start (or re-run) a check.
     *
     * @param \cm_info|\stdClass $cm
     * @param int $userid
     * @param string $itemkey
     * @param string $text
     * @param string $identifier Hash of the submitted item, to see later if it changed.
     * @return string '' on success, else the message to show.
     */
    public static function start($cm, int $userid, string $itemkey, string $text, string $identifier): string {
        global $DB, $CFG;
        if (trim($text) === '') {
            return get_string('error_empty', 'plagiarism_pcx');
        }
        $modules = self::settings((int) $cm->id)['modules'];
        if (!$modules) {
            return get_string('error_nomodules', 'plagiarism_pcx');
        }

        $row = self::get((int) $cm->id, $userid, $itemkey);
        $previous = $row && $row->scanid !== '' ? $row->scanid : '';
        $body = [
            'text' => $text,
            'modules' => $modules,
            'source' => \core_text::substr((string) parse_url($CFG->wwwroot, PHP_URL_HOST), 0, 60),
            'title' => \core_text::substr(format_string($cm->name, true, ['escape' => false]), 0, 255),
        ];
        // One opaque key per submission version and set of checks: no Moodle ids leave the site.
        $idem = 'moodle-' . hash_hmac('sha256', implode('|', [$cm->id, $userid, $itemkey, $identifier,
            implode(',', $modules)]), (string) $CFG->siteidentifier);
        $headers = ['Idempotency-Key' => $idem];

        $res = null;
        if ($previous !== '') {
            $res = api::request('POST', '/scans', $body + ['previous_scan_id' => $previous], $headers);
            // The earlier scan was deleted in the app: start a new one.
            if ($res['status'] === 404 && ($res['body']['error']['code'] ?? '') === 'not_found') {
                $previous = '';
                $headers['Idempotency-Key'] = $idem . '-new';
            }
        }
        if ($previous === '') {
            $res = api::request('POST', '/scans', $body, $headers);
        }
        if ($res['status'] !== 202) {
            return api::message($res);
        }
        if (empty($res['body']['modules']['accepted'])) {
            $rejected = $res['body']['modules']['rejected'] ?? [];
            return (string) ($rejected[0]['message'] ?? get_string('error_server', 'plagiarism_pcx'));
        }

        $now = time();
        $record = (object) [
            'cm' => (int) $cm->id,
            'userid' => $userid,
            'itemkey' => $itemkey,
            'identifier' => $identifier,
            'scanid' => (string) $res['body']['scan_id'],
            'modules' => implode(',', $modules),
            'status' => 'pending',
            'results' => null,
            'reporturl' => '',
            'error' => '',
            'timemodified' => $now,
        ];
        if ($row) {
            $record->id = $row->id;
            $DB->update_record('plagiarism_pcx_files', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('plagiarism_pcx_files', $record);
        }
        return '';
    }

    /**
     * Ask the API how a pending check is doing and save the answer.
     *
     * @param \stdClass $row
     * @return \stdClass The updated row.
     */
    public static function refresh(\stdClass $row): \stdClass {
        global $DB;
        if ($row->status !== 'pending') {
            return $row;
        }
        $res = api::request('GET', '/scans/' . rawurlencode($row->scanid));
        $row->timemodified = time();
        if ($res['status'] === 404) {
            $row->status = 'failed';
            $row->error = get_string('error_scangone', 'plagiarism_pcx');
        } else if ($res['status'] === 200) {
            $results = [];
            $done = true;
            foreach ((array) ($res['body']['modules'] ?? []) as $m) {
                if (!is_array($m) || !in_array($m['module'] ?? '', self::MODULES, true)) {
                    continue;
                }
                $status = (string) ($m['status'] ?? '');
                if (!in_array($status, ['completed', 'failed'], true)) {
                    $done = false;
                    continue;
                }
                $results[$m['module']] = self::summarise($m);
            }
            $row->reporturl = clean_param((string) ($res['body']['report_url'] ?? ''), PARAM_URL);
            if ($done) {
                $row->status = 'done';
                $row->results = json_encode($results);
            }
        }
        // Other errors (rate limit, network): stay pending and try again later.
        $DB->update_record('plagiarism_pcx_files', $row);
        return $row;
    }

    /**
     * The few numbers shown next to a submission, from one module's result.
     *
     * @param array $m
     * @return array
     */
    private static function summarise(array $m): array {
        if (($m['status'] ?? '') === 'failed') {
            return ['failed' => true, 'message' => (string) ($m['error']['message'] ?? '')];
        }
        $summary = (array) ($m['result']['summary'] ?? []);
        $findings = count((array) ($m['result']['findings'] ?? []));
        switch ($m['module']) {
            case 'plagiarism':
                return ['similarity' => (float) ($summary['similarity'] ?? 0), 'findings' => $findings];
            case 'ai_detection':
                if (!empty($summary['refusal'])) {
                    return ['refusal' => (string) ($summary['refusal']['message'] ?? '')];
                }
                return [
                    'score' => isset($summary['score']) ? (int) $summary['score'] : null,
                    'likely' => (float) ($summary['likely_ai_share'] ?? 0),
                    'band' => (string) ($summary['band'] ?? ''),
                ];
            case 'readability':
                return [
                    'ease' => isset($summary['flesch_reading_ease'])
                        ? max(0, min(100, (int) round($summary['flesch_reading_ease']))) : null,
                    'issues' => (int) ($summary['issues'] ?? $findings),
                ];
            default:
                return ['issues' => (int) ($summary['issues'] ?? $findings)];
        }
    }
}
