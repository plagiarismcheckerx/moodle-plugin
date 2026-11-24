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

namespace plagiarism_pcx\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Privacy: the checks stored per submission, and the text sent to Plagiarism Checker X.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_plagiarism\privacy\plagiarism_provider,
    \core_plagiarism\privacy\plagiarism_user_provider,
    \core_privacy\local\metadata\provider {
    /**
     * Describe the data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('plagiarism_pcx_files', [
            'userid' => 'privacy:metadata:plagiarism_pcx_files:userid',
            'scanid' => 'privacy:metadata:plagiarism_pcx_files:scanid',
            'results' => 'privacy:metadata:plagiarism_pcx_files:results',
            'reporturl' => 'privacy:metadata:plagiarism_pcx_files:reporturl',
            'timecreated' => 'privacy:metadata:plagiarism_pcx_files:timecreated',
        ], 'privacy:metadata:plagiarism_pcx_files');

        $collection->add_external_location_link('plagiarismcheckerx', [
            'text' => 'privacy:metadata:plagiarismcheckerx:text',
            'title' => 'privacy:metadata:plagiarismcheckerx:title',
            'source' => 'privacy:metadata:plagiarismcheckerx:source',
        ], 'privacy:metadata:plagiarismcheckerx');

        $collection->add_subsystem_link('core_plagiarism', [], 'privacy:metadata:core_plagiarism');
        return $collection;
    }

    /**
     * Export a user's checks in a module context.
     *
     * @param int $userid
     * @param \context $context
     * @param array $subcontext
     * @param array $linkarray
     */
    public static function export_plagiarism_user_data(int $userid, \context $context, array $subcontext, array $linkarray) {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $rows = $DB->get_records('plagiarism_pcx_files', ['cm' => $context->instanceid, 'userid' => $userid]);
        foreach ($rows as $row) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, [get_string('privacy:path', 'plagiarism_pcx'), $row->id]),
                (object) [
                    'scanid' => $row->scanid,
                    'status' => $row->status,
                    'results' => $row->results,
                    'reporturl' => $row->reporturl,
                    'timecreated' => \core_privacy\local\request\transform::datetime($row->timecreated),
                ]
            );
        }
    }

    /**
     * Delete all checks in a context.
     *
     * @param \context $context
     */
    public static function delete_plagiarism_for_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $DB->delete_records('plagiarism_pcx_files', ['cm' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's checks in a context.
     *
     * @param int $userid
     * @param \context $context
     */
    public static function delete_plagiarism_for_user(int $userid, \context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $DB->delete_records('plagiarism_pcx_files', ['cm' => $context->instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete several users' checks in a context.
     *
     * @param array $userids
     * @param \context $context
     */
    public static function delete_plagiarism_for_users(array $userids, \context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE || !$userids) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cm'] = $context->instanceid;
        $DB->delete_records_select('plagiarism_pcx_files', "cm = :cm AND userid $in", $params);
    }
}
