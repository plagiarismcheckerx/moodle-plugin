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
 * English strings.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'API key';
$string['changed'] = 'Changed since this check.';
$string['check'] = 'Check';
$string['checking'] = 'Checking…';
$string['checks'] = 'Checks to run';
$string['connect'] = 'Connect to {$a}';
$string['connect_cancelled'] = 'Connection cancelled. Nothing was changed.';
$string['connect_expired'] = 'That connection attempt expired. Click Connect again.';
$string['connect_intro'] = 'Connect this site to your {$a} account. Checks spend words from that account.';
$string['connect_noregister'] = 'Plagiarism Checker X refused to register this site. Check that the API is switched on for your account, or use an API key instead.';
$string['connect_notapproved'] = 'Plagiarism Checker X did not approve the connection. Try again.';
$string['connect_notoken'] = 'Plagiarism Checker X did not issue a token. Click Connect again.';
$string['connected'] = 'Connected.';
$string['connected_key'] = 'This site is connected to {$a} with an API key.';
$string['connected_oauth'] = 'This site is connected to your {$a} account.';
$string['connection'] = 'Connection';
$string['defaulton'] = 'Turn the plugin on for new assignments by default';
$string['disclosure'] = 'Message shown to students when they submit';
$string['disclosure_default'] = 'Your teacher may check this submission with Plagiarism Checker X. The text is sent to Plagiarism Checker X for checking.';
$string['disconnect'] = 'Disconnect';
$string['disconnected'] = 'Disconnected.';
$string['enabled'] = 'Enable Plagiarism Checker X';
$string['error_disabled'] = 'Plagiarism Checker X is not turned on for this assignment.';
$string['error_empty'] = 'This submission has no text to check.';
$string['error_nomodules'] = 'No checks are chosen for this assignment. Choose them in the assignment settings.';
$string['error_nosubmission'] = 'That submission was not found.';
$string['error_notconnected'] = 'This site is not connected yet. Ask an administrator to connect it in Site administration → Plugins → Plagiarism → Plagiarism Checker X.';
$string['error_scangone'] = 'This check was deleted in Plagiarism Checker X. Run it again.';
$string['error_server'] = 'Plagiarism Checker X could not complete this request. Try again in a minute.';
$string['error_timeout'] = 'This check did not finish. Run it again.';
$string['error_unreachable'] = 'Could not reach Plagiarism Checker X ({$a}). Check the site can make outgoing connections, then try again.';
$string['extrawords'] = 'Extra words';
$string['failed'] = 'failed';
$string['issues'] = '{$a} issues';
$string['key_intro'] = 'Or paste an API key from your <a href="{$a->url}" target="_blank" rel="noopener">{$a->name} account</a>:';
$string['key_invalid'] = 'That does not look like an API key.';
$string['module_ai_detection'] = 'AI Detection';
$string['module_grammar'] = 'Grammar';
$string['module_plagiarism'] = 'Plagiarism';
$string['module_readability'] = 'Readability';
$string['openreport'] = 'Open report';
$string['options'] = 'Options';
$string['pcx:check'] = 'Check submissions with Plagiarism Checker X';
$string['pcx:enable'] = 'Turn Plagiarism Checker X on for an activity';
$string['pcx:viewreport'] = 'See Plagiarism Checker X results';
$string['plagiarismoff'] = 'Plagiarism plugins are switched off for this site. Turn on <a href="{$a}">Enable plagiarism plugins</a> first.';
$string['plan'] = 'Plan';
$string['planended'] = 'Your plan has ended, so checks are refused until you renew it.';
$string['pluginname'] = 'Plagiarism Checker X';
$string['privacy:metadata:core_plagiarism'] = 'This plugin is called by the Moodle plagiarism subsystem.';
$string['privacy:metadata:plagiarism_pcx_files'] = 'The checks run on submissions.';
$string['privacy:metadata:plagiarism_pcx_files:reporturl'] = 'The link to the full report in Plagiarism Checker X.';
$string['privacy:metadata:plagiarism_pcx_files:results'] = 'The scores of the check.';
$string['privacy:metadata:plagiarism_pcx_files:scanid'] = 'The check\'s id in Plagiarism Checker X.';
$string['privacy:metadata:plagiarism_pcx_files:timecreated'] = 'When the check was run.';
$string['privacy:metadata:plagiarism_pcx_files:userid'] = 'The user who submitted the work.';
$string['privacy:metadata:plagiarismcheckerx'] = 'When a teacher checks a submission, its text is sent to Plagiarism Checker X, which checks it and keeps the result in the account that connected the site. No name or email address is sent.';
$string['privacy:metadata:plagiarismcheckerx:source'] = 'The site\'s host name.';
$string['privacy:metadata:plagiarismcheckerx:text'] = 'The text of the submission.';
$string['privacy:metadata:plagiarismcheckerx:title'] = 'The assignment\'s name.';
$string['privacy:path'] = 'Plagiarism Checker X';
$string['recheck'] = 'Re-check';
$string['report'] = 'Report';
$string['savekey'] = 'Save key';
$string['short_ai_detection'] = 'AI';
$string['short_grammar'] = 'Grammar';
$string['short_plagiarism'] = 'Plagiarism';
$string['short_readability'] = 'Reading';
$string['started'] = 'Check started. The result shows here in a minute or two.';
$string['task_updateresults'] = 'Fetch Plagiarism Checker X results';
$string['tryagain'] = 'Try again';
$string['unsupported'] = 'This file type cannot be checked (supported: .docx, .odt, .txt, .md, .html).';
$string['useforactivity'] = 'Use Plagiarism Checker X';
$string['useforactivity_help'] = 'Teachers can check each submission with Plagiarism Checker X. Nothing is checked automatically: a check runs only when a teacher clicks Check, and it spends words from the connected account.';
$string['wordsleft'] = '{$a} words left';
