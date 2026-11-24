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
 * "Connect to Plagiarism Checker X": OAuth 2.1 with PKCE. The site registers itself once as a
 * public client (dynamic client registration), the administrator approves on the brand's own
 * page, and the tokens stay on the server. Refresh tokens rotate, so a refresh runs under a lock
 * and stores the new pair before anything else may use it.
 *
 * @package    plagiarism_pcx
 * @copyright  2026 Plagiarism Checker X
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class oauth {
    /** @var string Scopes asked for. */
    public const SCOPES = 'scan account:read';

    /**
     * Where the brand sends the administrator back.
     *
     * @return \moodle_url
     */
    public static function redirect_uri(): \moodle_url {
        return new \moodle_url('/plagiarism/pcx/connect.php');
    }

    /**
     * The authorize URL to send the administrator to, or null if registration failed.
     *
     * @return \moodle_url|null
     */
    public static function start(): ?\moodle_url {
        global $SESSION;
        $clientid = self::client_id();
        if ($clientid === '') {
            return null;
        }
        $verifier = self::random(64);
        $state = self::random(32);
        $SESSION->plagiarism_pcx_oauth = ['state' => $state, 'verifier' => $verifier, 'client_id' => $clientid,
            'at' => time()];

        return new \moodle_url(brand::api_base() . '/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $clientid,
            'redirect_uri' => self::redirect_uri()->out(false),
            'scope' => self::SCOPES,
            'state' => $state,
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Finish the return step.
     *
     * @param string $state
     * @param string $code
     * @param string $error
     * @return string '' on success, else the message to show.
     */
    public static function finish(string $state, string $code, string $error): string {
        global $SESSION;
        $pending = $SESSION->plagiarism_pcx_oauth ?? null;
        unset($SESSION->plagiarism_pcx_oauth);

        if (
            !is_array($pending) || $state === '' || !hash_equals((string) $pending['state'], $state)
                || (int) $pending['at'] < time() - 900
        ) {
            return get_string('connect_expired', 'plagiarism_pcx');
        }
        if ($error === 'access_denied') {
            return get_string('connect_cancelled', 'plagiarism_pcx');
        }
        if ($code === '') {
            return get_string('connect_notapproved', 'plagiarism_pcx');
        }
        $tokens = self::token_request([
            'grant_type' => 'authorization_code',
            'client_id' => $pending['client_id'],
            'redirect_uri' => self::redirect_uri()->out(false),
            'code' => $code,
            'code_verifier' => $pending['verifier'],
        ]);
        if ($tokens === null) {
            return get_string('connect_notoken', 'plagiarism_pcx');
        }
        self::store_tokens($tokens, ['connected_at' => time()]);
        return '';
    }

    /**
     * A live access token, refreshing when needed. $force refreshes even if the stored one looks
     * valid (the API just answered 401). Returns '' when the connection is gone.
     *
     * @param bool $force
     * @return string
     */
    public static function refresh(bool $force): string {
        $before = connection::get(true);
        if (empty($before['refresh_token'])) {
            return '';
        }
        $factory = \core\lock\lock_config::get_lock_factory('plagiarism_pcx');
        $lock = $factory->get_lock('refresh', 10);
        if (!$lock) {
            return '';
        }
        try {
            // Another request may have refreshed while this one waited for the lock.
            $c = connection::get(true);
            if (($c['access_token'] ?? '') !== ($before['access_token'] ?? '') || !$force) {
                if (!empty($c['access_token']) && (int) ($c['expires_at'] ?? 0) > time() + 60) {
                    return (string) $c['access_token'];
                }
            }
            $tokens = self::token_request([
                'grant_type' => 'refresh_token',
                'client_id' => (string) ($c['client_id'] ?? ''),
                'refresh_token' => (string) $c['refresh_token'],
            ]);
            if ($tokens === null) {
                return '';
            }
            self::store_tokens($tokens, []);
            return (string) $tokens['access_token'];
        } finally {
            $lock->release();
        }
    }

    /**
     * Revoke the refresh token and forget the connection (the registered client is kept).
     */
    public static function disconnect(): void {
        $c = connection::get(true);
        if (!empty($c['refresh_token'])) {
            $curl = new \curl();
            $curl->post(brand::api_base() . '/oauth/revoke', [
                'token' => (string) $c['refresh_token'],
                'token_type_hint' => 'refresh_token',
                'client_id' => (string) ($c['client_id'] ?? ''),
            ], ['CURLOPT_TIMEOUT' => 15]);
        }
        connection::save(array_intersect_key($c, ['client_id' => true, 'client_for' => true]));
    }

    /**
     * This site's OAuth client id, registered once (again if the site moved).
     *
     * @return string
     */
    private static function client_id(): string {
        global $SITE;
        $c = connection::get(true);
        $for = brand::api_base() . '|' . self::redirect_uri()->out(false);
        if (!empty($c['client_id']) && ($c['client_for'] ?? '') === $for) {
            return (string) $c['client_id'];
        }
        $site = trim(format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]));
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $raw = $curl->post(brand::api_base() . '/oauth/register', json_encode([
            'client_name' => \core_text::substr('Moodle – ' . $site, 0, 100),
            'redirect_uris' => [self::redirect_uri()->out(false)],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => self::SCOPES,
        ]), ['CURLOPT_TIMEOUT' => 15]);
        $body = $curl->get_errno() ? null : json_decode((string) $raw, true);
        if (!is_array($body) || empty($body['client_id'])) {
            return '';
        }
        $c['client_id'] = (string) $body['client_id'];
        $c['client_for'] = $for;
        connection::save($c);
        return $c['client_id'];
    }

    /**
     * POST to the token endpoint.
     *
     * @param array $form
     * @return array|null
     */
    private static function token_request(array $form): ?array {
        $curl = new \curl();
        $curl->setHeader(['Accept: application/json']);
        $raw = $curl->post(brand::api_base() . '/oauth/token', $form, ['CURLOPT_TIMEOUT' => 15]);
        if ($curl->get_errno() || (int) ($curl->get_info()['http_code'] ?? 0) !== 200) {
            return null;
        }
        $body = json_decode((string) $raw, true);
        return is_array($body) && !empty($body['access_token']) && !empty($body['refresh_token']) ? $body : null;
    }

    /**
     * Save a token pair, dropping any API key.
     *
     * @param array $tokens
     * @param array $extra
     */
    private static function store_tokens(array $tokens, array $extra): void {
        $c = connection::get(true);
        unset($c['api_key']);
        connection::save(array_merge($c, $extra, [
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => (string) $tokens['refresh_token'],
            'expires_at' => time() + max(60, (int) ($tokens['expires_in'] ?? 3600)),
        ]));
    }

    /**
     * URL-safe random string.
     *
     * @param int $bytes
     * @return string
     */
    private static function random(int $bytes): string {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * PKCE S256 challenge.
     *
     * @param string $verifier
     * @return string
     */
    private static function challenge(string $verifier): string {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
