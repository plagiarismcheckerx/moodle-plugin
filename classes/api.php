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

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

/**
 * The public API client. Every call runs on the server with the site's key or Connect token;
 * an API refusal comes back as code + message exactly as the API wrote them.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api {
    /** @var string Public API prefix. */
    public const PREFIX = '/api/public/v1';

    /**
     * Call the public API.
     *
     * @param string $method GET, POST, DELETE.
     * @param string $path e.g. '/scans'.
     * @param array|null $body JSON body.
     * @param array $headers Extra headers, name => value.
     * @return array{status:int, body:array}
     */
    public static function request(string $method, string $path, ?array $body = null, array $headers = []): array {
        $token = self::bearer();
        if ($token === '') {
            return self::refusal(401, 'not_connected', get_string('error_notconnected', 'plagiarism_pcx'));
        }
        $res = self::send($method, $path, $body, $headers, $token);

        // A Connect token can be revoked or expire early: refresh once and retry.
        if ($res['status'] === 401 && connection::mode() === 'connect') {
            $fresh = oauth::refresh(true);
            if ($fresh !== '') {
                $res = self::send($method, $path, $body, $headers, $fresh);
            }
        }
        return $res;
    }

    /**
     * The bearer for the next call: the API key, or a live Connect access token.
     *
     * @return string
     */
    private static function bearer(): string {
        $c = connection::get();
        if (!empty($c['api_key'])) {
            return (string) $c['api_key'];
        }
        if (empty($c['refresh_token'])) {
            return '';
        }
        if (!empty($c['access_token']) && (int) ($c['expires_at'] ?? 0) > time() + 60) {
            return (string) $c['access_token'];
        }
        return oauth::refresh(false);
    }

    /**
     * One HTTP call.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @param array $headers
     * @param string $token
     * @return array{status:int, body:array}
     */
    private static function send(string $method, string $path, ?array $body, array $headers, string $token): array {
        global $CFG;
        $curl = new \curl(['ignoresecurity' => false]);
        $lines = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'User-Agent: PlagiarismCheckerX-Moodle/' . get_config('plagiarism_pcx', 'version') . '; ' . $CFG->wwwroot,
        ];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        if ($body !== null) {
            $lines[] = 'Content-Type: application/json';
        }
        $curl->setHeader($lines);
        $options = ['CURLOPT_TIMEOUT' => 30, 'CURLOPT_CONNECTTIMEOUT' => 10];
        $url = brand::api_base() . self::PREFIX . $path;
        $payload = $body === null ? '' : json_encode($body);

        switch ($method) {
            case 'POST':
                $raw = $curl->post($url, $payload, $options);
                break;
            case 'DELETE':
                $raw = $curl->delete($url, [], $options);
                break;
            default:
                $raw = $curl->get($url, [], $options);
        }

        if ($curl->get_errno()) {
            return self::refusal(503, 'unreachable', get_string('error_unreachable', 'plagiarism_pcx', $curl->error));
        }
        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        $decoded = json_decode((string) $raw, true);
        $out = ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
        if ($status >= 400 && empty($out['body']['error'])) {
            $out['body'] = ['error' => ['code' => 'server_error', 'message' => get_string('error_server', 'plagiarism_pcx')]];
        }
        return $out;
    }

    /**
     * A refusal in the API's own shape.
     *
     * @param int $status
     * @param string $code
     * @param string $message
     * @return array{status:int, body:array}
     */
    public static function refusal(int $status, string $code, string $message): array {
        return ['status' => $status, 'body' => ['error' => ['code' => $code, 'message' => $message]]];
    }

    /**
     * The API's message from a refusal.
     *
     * @param array $res
     * @return string
     */
    public static function message(array $res): string {
        $msg = (string) ($res['body']['error']['message'] ?? '');
        return $msg !== '' ? $msg : get_string('error_server', 'plagiarism_pcx');
    }
}
