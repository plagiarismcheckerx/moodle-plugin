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
 * The brand's hosts. A test site may point at another backend with
 * $CFG->plagiarism_pcx_api_base / $CFG->plagiarism_pcx_app_url in config.php.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class brand {
    /** @var string Product name. */
    public const NAME = 'Plagiarism Checker X';

    /** @var string API host. */
    public const API_BASE = 'https://api.plagiarismcheckerx.com';

    /** @var string Web app, for "Get an API key". */
    public const APP_URL = 'https://plagiarismcheckerx.com';

    /** @var string What an API key looks like. */
    public const KEY_HINT = 'pcx_live_…';

    /**
     * The API host, without a trailing slash.
     *
     * @return string
     */
    public static function api_base(): string {
        global $CFG;
        $base = !empty($CFG->plagiarism_pcx_api_base) ? (string) $CFG->plagiarism_pcx_api_base : self::API_BASE;
        return rtrim($base, '/');
    }

    /**
     * The web app, without a trailing slash.
     *
     * @return string
     */
    public static function app_url(): string {
        global $CFG;
        $url = !empty($CFG->plagiarism_pcx_app_url) ? (string) $CFG->plagiarism_pcx_app_url : self::APP_URL;
        return rtrim($url, '/');
    }
}
