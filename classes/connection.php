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
 * The site's link to the account: an API key, or Connect tokens. Kept in plugin config as one
 * JSON value; never sent to the browser.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class connection {
    /** @var string Config name. */
    public const NAME = 'connection';

    /**
     * The stored connection.
     *
     * @param bool $fresh Skip the config cache (another request may have refreshed the tokens).
     * @return array
     */
    public static function get(bool $fresh = false): array {
        global $DB;
        if ($fresh) {
            $raw = $DB->get_field('config_plugins', 'value', ['plugin' => 'plagiarism_pcx', 'name' => self::NAME]);
        } else {
            $raw = get_config('plagiarism_pcx', self::NAME);
        }
        $value = $raw ? json_decode((string) $raw, true) : null;
        return is_array($value) ? $value : [];
    }

    /**
     * Store the connection.
     *
     * @param array $value
     */
    public static function save(array $value): void {
        set_config(self::NAME, json_encode($value), 'plagiarism_pcx');
    }

    /**
     * Whether checks can run.
     *
     * @return bool
     */
    public static function is_connected(): bool {
        $c = self::get();
        return !empty($c['api_key']) || !empty($c['refresh_token']);
    }

    /**
     * How the site is connected.
     *
     * @return string 'key', 'connect' or ''.
     */
    public static function mode(): string {
        $c = self::get();
        if (!empty($c['api_key'])) {
            return 'key';
        }
        return empty($c['refresh_token']) ? '' : 'connect';
    }
}
