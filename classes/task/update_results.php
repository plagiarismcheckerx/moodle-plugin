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

namespace plagiarism_pcx\task;

use plagiarism_pcx\checker;
use plagiarism_pcx\connection;

/**
 * Fetch results of running checks, so they are ready when the teacher comes back.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_results extends \core\task\scheduled_task {
    /** @var int A check still running after this long is given up. */
    public const GIVE_UP = 2 * DAYSECS;

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_updateresults', 'plagiarism_pcx');
    }

    /**
     * Run.
     */
    public function execute() {
        global $DB;
        if (!connection::is_connected()) {
            return;
        }
        $now = time();
        $DB->execute(
            "UPDATE {plagiarism_pcx_files} SET status = 'failed', error = :error, timemodified = :now
                       WHERE status = 'pending' AND timecreated < :cutoff",
            ['error' => get_string('error_timeout', 'plagiarism_pcx'), 'now' => $now, 'cutoff' => $now - self::GIVE_UP]
        );

        $rows = $DB->get_records_select(
            'plagiarism_pcx_files',
            "status = 'pending' AND timemodified < :since",
            ['since' => $now - 20],
            'timemodified ASC',
            '*',
            0,
            100
        );
        foreach ($rows as $row) {
            $row = checker::refresh($row);
            mtrace('plagiarism_pcx: scan ' . $row->scanid . ' ' . $row->status);
        }
    }
}
