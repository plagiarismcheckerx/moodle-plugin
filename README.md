# Plagiarism Checker X for Moodle (plagiarism_pcx)

Check assignment submissions for plagiarism and AI writing with
[Plagiarism Checker X](https://plagiarismcheckerx.com), right from the Moodle grading page.

## Features

- Checks online text answers and submitted files (.docx, .odt, .txt, .md, .html).
- Four checks, chosen per assignment: Plagiarism, AI Detection, Grammar, Readability.
- Nothing runs automatically: a teacher clicks **Check** next to a submission. Each check spends
  words from the connected Plagiarism Checker X account.
- Results appear next to the submission as one compact row (similarity %, AI score) with a link
  to the full report in Plagiarism Checker X.
- If the student changes the submission, it shows "Changed since this check" and **Re-check**;
  a re-check is charged only for the edited words.
- Students see a notice on the submission page that their work may be checked (editable).

## Requirements

- Moodle 4.1 or later.
- A Plagiarism Checker X account (https://plagiarismcheckerx.com) with words on its plan.
- The Moodle server must be able to make outgoing HTTPS requests to api.plagiarismcheckerx.com.

## Installation

1. Copy this folder to `plagiarism/pcx` in your Moodle (in Moodle 5.1+: `public/plagiarism/pcx`),
   or install the ZIP from Site administration → Plugins → Install plugins.
2. Visit Site administration → Notifications to finish the install.
3. Turn on **Enable plagiarism plugins** (Site administration → Development → Advanced features).
4. Open Site administration → Plugins → Plagiarism → Plagiarism Checker X, tick **Enable
   Plagiarism Checker X**, and connect the site: click **Connect to Plagiarism Checker X**, or
   paste an API key from your account.
5. In an assignment's settings, open the **Plagiarism Checker X** section, tick **Use Plagiarism
   Checker X**, and choose the checks.

## Capabilities

- `plagiarism/pcx:enable`: turn the plugin on for an activity (editing teachers, managers).
- `plagiarism/pcx:check`: run a check (teachers, managers).
- `plagiarism/pcx:viewreport`: see results (teachers, managers). Students do not see results.

## Privacy and external service

When a teacher checks a submission, the plugin sends the submission's text, the assignment's
name and the site's host name to Plagiarism Checker X (https://api.plagiarismcheckerx.com),
which checks it and keeps the result in the account that connected the site. No student name or
email address is sent. Connecting the site also sends the site's name and the address of the
connect page. Nothing is sent until an administrator connects the site, and then only when a
teacher clicks Check or Re-check.

- Terms: https://plagiarismcheckerx.com/terms-conditions
- Privacy policy: https://plagiarismcheckerx.com/privacy-policy

The plugin implements the Moodle Privacy API (export and deletion of stored check results).

## Limitations

- PDF files and group submissions are not checked yet.
- Assignments (mod_assign) only.

## License

GNU GPL v3 or later. © 2026 Plagiarism Checker X.
