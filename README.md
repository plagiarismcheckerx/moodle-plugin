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

## External service and privacy

This plugin relies on an external service: **Plagiarism Checker X**, run by Plagiarism Checker X
outside of Moodle. It does not work without a Plagiarism Checker X account, and checks spend
words from that account (paid plans; see https://plagiarismcheckerx.com/pricing).

**Where data goes:** `https://api.plagiarismcheckerx.com` over HTTPS.

**When data is sent:** nothing is sent until an administrator connects the site. After that,
data is sent only when a teacher clicks **Check** or **Re-check** on a submission. Nothing is
sent automatically when students submit.

**What is sent when a teacher checks a submission:**

- The text of the submission: online text, or text read on the Moodle server from the
  submitted file. The file itself is not uploaded.
- The assignment's name.
- The site's host name (for example `moodle.example.edu`).
- The checks chosen for the assignment.
- A random-looking request key (a one-way hash made on the Moodle server), used only so a
  repeated click doesn't start (and charge for) a second check. It contains no Moodle ids.

**What is sent when an administrator connects the site:** the site's full name and the address
of the plugin's connect page (OAuth client registration), or nothing extra if an API key is pasted
instead.

**What is not sent:** student names, usernames, email addresses, Moodle user ids, or any other profile data.

**What Plagiarism Checker X keeps:** the submitted text and the check results are stored in the
Plagiarism Checker X account that connected the site, where the account owner can view and delete
them. Moodle keeps only the scores, the report link and the check's id.

Students see a notice on the submission page that their work may be sent to Plagiarism Checker X
for checking (the wording can be changed in the plugin settings).

- Terms of service: https://plagiarismcheckerx.com/terms-conditions
- Privacy policy: https://plagiarismcheckerx.com/privacy-policy

The plugin implements the Moodle Privacy API: it declares the data sent to Plagiarism Checker X
and supports export and deletion of the check results stored in Moodle.

## Limitations

- PDF files and group submissions are not checked yet.
- Assignments (mod_assign) only.

## License

GNU GPL v3 or later. © 2026 Plagiarism Checker X.
