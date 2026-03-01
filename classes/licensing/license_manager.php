<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_aceengine\licensing;
/**
 * License manager class for Pro licensing verification.
 *
 * Manages the activation, verification, and status caching of Pro
 * license keys. Communicates with a remote license server and uses
 * HMAC verification to ensure response integrity. Supports a 7-day
 * grace period after license expiry.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license_manager {
    /** @var string License status: active. */
    public const STATUS_ACTIVE = 'active';

    /** @var string License status: inactive. */
    public const STATUS_INACTIVE = 'inactive';

    /** @var string License status: expired. */
    public const STATUS_EXPIRED = 'expired';

    /** @var string License status: invalid. */
    public const STATUS_INVALID = 'invalid';

    /** @var int Grace period duration in seconds (7 days). */
    private const GRACE_PERIOD_SECONDS = 604800;

    /** @var int Minimum interval between license server checks in seconds (1 hour). */
    private const CHECK_INTERVAL = 3600;

    /** @var string License server base URL. */
    public const LICENSE_SERVER_URL = 'https://plugins.letstudy.gr';

    /**
     * Check if the license is currently valid (active or within grace period).
     *
     * Static convenience method for Pro plugins to check license validity.
     *
     * @return bool True if license is valid.
     */
    public static function is_valid(): bool {
        $instance = new self();
        return $instance->is_pro_enabled();
    }

    /**
     * Get the current license status as a string.
     *
     * @return string One of 'active', 'expired', 'invalid', 'inactive'.
     */
    public static function get_status(): string {
        $instance = new self();
        return $instance->get_license_status();
    }

    /**
     * Get the number of grace period days remaining.
     *
     * @return int Days remaining, or 0 if not in grace period.
     */
    public static function get_grace_days(): int {
        $instance = new self();
        return $instance->get_grace_days_remaining();
    }

    /**
     * Get the license expiry timestamp.
     *
     * @return int|null The expiry timestamp, or null if no license.
     */
    public static function get_expiry_date(): ?int {
        $instance = new self();
        $cached = $instance->get_cached_license();
        if (!$cached || empty($cached->expirydate)) {
            return null;
        }
        return (int) $cached->expirydate;
    }

    /**
     * Get the cached AI configuration from the license server.
     *
     * @return object|null The AI config object, or null if unavailable.
     */
    public static function get_ai_config(): ?object {
        $instance = new self();
        $cached = $instance->get_cached_license();
        if (!$cached || empty($cached->ai_config)) {
            return null;
        }
        $config = json_decode($cached->ai_config);
        return is_object($config) ? $config : null;
    }

    /**
     * Get the remaining credit balance.
     *
     * @return int The number of credits remaining.
     */
    public static function get_credits_remaining(): int {
        $instance = new self();
        $cached = $instance->get_cached_license();
        if (!$cached) {
            return 0;
        }
        return (int) ($cached->credits_remaining ?? 0);
    }

    /**
     * Get the total credit balance.
     *
     * @return int The total number of credits allocated.
     */
    public static function get_credits_total(): int {
        $instance = new self();
        $cached = $instance->get_cached_license();
        if (!$cached) {
            return 0;
        }
        return (int) ($cached->credits_total ?? 0);
    }

    /**
     * Check if Pro features should be active.
     *
     * Returns true if the license is currently valid or within the
     * grace period after expiry.
     *
     * @return bool True if Pro features are enabled.
     */
    public function is_pro_enabled(): bool {
        $licensekey = get_config('local_aceengine', 'licensekey');
        if (empty($licensekey)) {
            return false;
        }

        $status = $this->get_license_status();

        if ($status === self::STATUS_ACTIVE) {
            return true;
        }

        if ($status === self::STATUS_EXPIRED) {
            return $this->is_in_grace_period();
        }

        return false;
    }

    /**
     * Verify the license with the remote license server.
     *
     * Sends the license key and site domain to the configured license
     * server endpoint. Validates the server response using HMAC-SHA256
     * with the license key as the secret. Caches the result in the
     * local_aceengine_license table.
     *
     * @return array Associative array with 'status', 'message', and optional 'expirydate'.
     */
    public function verify_license(): array {
        global $CFG;

        $licensekey = get_config('local_aceengine', 'licensekey');
        if (empty($licensekey)) {
            return [
                'status' => self::STATUS_INVALID,
                'message' => get_string('licensestatus_none', 'local_aceengine'),
            ];
        }

        $domain = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: $CFG->wwwroot;

        // Build the verification request.
        $postdata = [
            'license_key' => $licensekey,
            'domain' => $domain,
            'plugin' => 'local_aceengine',
            'version' => get_config('local_aceengine', 'version') ?: '1.0.0',
        ];

        $url = self::LICENSE_SERVER_URL . '/verify';

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');

        $response = $curl->post($url, json_encode($postdata));

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200 || empty($response)) {
            // Network failure -- retain existing status if within check interval.
            $cached = $this->get_cached_license();
            if ($cached && ((time() - (int) $cached->lastcheck) < self::CHECK_INTERVAL * 6)) {
                return [
                    'status' => $cached->status,
                    'message' => 'License server unreachable. Using cached status.',
                    'expirydate' => $cached->expirydate,
                ];
            }

            return [
                'status' => self::STATUS_INVALID,
                'message' => 'Unable to contact license server (HTTP ' . $httpcode . ').',
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [
                'status' => self::STATUS_INVALID,
                'message' => 'Invalid response from license server.',
            ];
        }

        // Verify HMAC signature of the response.
        if (!$this->verify_response_signature($data, $licensekey)) {
            return [
                'status' => self::STATUS_INVALID,
                'message' => 'License server response failed HMAC verification.',
            ];
        }

        $serverstatus = $data['status'] ?? self::STATUS_INVALID;
        $expirydate = $data['expiry_date'] ?? null;
        $message = $data['message'] ?? '';

        // Map server status to internal status.
        $internalstatus = match ($serverstatus) {
            'active', 'valid' => self::STATUS_ACTIVE,
            'expired' => self::STATUS_EXPIRED,
            default => self::STATUS_INVALID,
        };

        // Extract credit and AI config data from enhanced response.
        $creditsremaining = (int) ($data['credits_remaining'] ?? 0);
        $creditstotal = (int) ($data['credits_total'] ?? 0);
        $aiconfig = isset($data['ai_config']) ? json_encode($data['ai_config']) : null;

        // Cache the result.
        $this->cache_license_status(
            $licensekey,
            $domain,
            $internalstatus,
            $expirydate,
            $data['signature'] ?? '',
            $creditsremaining,
            $creditstotal,
            $aiconfig
        );

        // Determine display message.
        if ($internalstatus === self::STATUS_ACTIVE) {
            $message = get_string('licensestatus_valid', 'local_aceengine');
        } else if ($internalstatus === self::STATUS_EXPIRED && $this->is_in_grace_period()) {
            $daysremaining = $this->get_grace_days_remaining();
            $message = get_string('licensestatus_grace', 'local_aceengine', $daysremaining);
        } else if ($internalstatus === self::STATUS_EXPIRED) {
            $message = get_string('licensestatus_expired', 'local_aceengine');
        } else {
            $message = get_string('licensestatus_invalid', 'local_aceengine');
        }

        return [
            'status' => $internalstatus,
            'message' => $message,
            'expirydate' => $expirydate,
        ];
    }

    /**
     * Get the current cached license status.
     *
     * Returns the status string from the local_aceengine_license table
     * without contacting the license server.
     *
     * @return string The cached license status (active, expired, invalid, inactive).
     */
    public function get_license_status(): string {
        $cached = $this->get_cached_license();
        if (!$cached) {
            return self::STATUS_INACTIVE;
        }

        return $cached->status;
    }

    /**
     * Activate a license key.
     *
     * Sends an activation request to the license server and caches
     * the result. Replaces any previously activated license.
     *
     * @param string $key The license key to activate.
     * @return array Associative array with 'success' (bool) and 'message' (string).
     */
    public function activate_license(string $key): array {
        global $CFG;

        $key = trim($key);
        if (empty($key)) {
            return [
                'success' => false,
                'message' => 'License key cannot be empty.',
            ];
        }

        $domain = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: $CFG->wwwroot;

        $postdata = [
            'license_key' => $key,
            'domain' => $domain,
            'plugin' => 'local_aceengine',
            'action' => 'activate',
        ];

        $url = self::LICENSE_SERVER_URL . '/activate';

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');

        $response = $curl->post($url, json_encode($postdata));

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200 || empty($response)) {
            return [
                'success' => false,
                'message' => 'Unable to contact license server (HTTP ' . $httpcode . ').',
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'message' => 'Invalid response from license server.',
            ];
        }

        // Verify HMAC signature.
        if (!$this->verify_response_signature($data, $key)) {
            return [
                'success' => false,
                'message' => 'License server response failed HMAC verification.',
            ];
        }

        $success = !empty($data['success']) || ($data['status'] ?? '') === 'active';
        $expirydate = $data['expiry_date'] ?? null;

        if ($success) {
            // Save the license key to plugin config.
            set_config('licensekey', $key, 'local_aceengine');

            // Cache the license status.
            $internalstatus = self::STATUS_ACTIVE;
            $this->cache_license_status($key, $domain, $internalstatus, $expirydate, $data['signature'] ?? '');

            return [
                'success' => true,
                'message' => get_string('licensestatus_valid', 'local_aceengine'),
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? get_string('licensestatus_invalid', 'local_aceengine'),
        ];
    }

    /**
     * Deactivate the current license.
     *
     * Sends a deactivation request to the license server and removes
     * the cached license data. Clears the license key from config.
     *
     * @return bool True if deactivation was successful.
     */
    public function deactivate_license(): bool {
        global $CFG, $DB;

        $licensekey = get_config('local_aceengine', 'licensekey');
        if (empty($licensekey)) {
            return true; // Nothing to deactivate.
        }

        $domain = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: $CFG->wwwroot;

        // Attempt to notify the license server.
        $postdata = [
            'license_key' => $licensekey,
            'domain' => $domain,
            'plugin' => 'local_aceengine',
            'action' => 'deactivate',
        ];

        $url = self::LICENSE_SERVER_URL . '/deactivate';

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ]);
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');

        $curl->post($url, json_encode($postdata));
        // We proceed regardless of server response.

        // Remove cached license record.
        $DB->delete_records('local_aceengine_license', ['licensekey' => $licensekey]);

        // Clear the license key from config.
        set_config('licensekey', '', 'local_aceengine');

        return true;
    }

    /**
     * Check if the license is within its grace period.
     *
     * The grace period is 7 days after the license expiry date. During
     * this period, Pro features remain enabled to give administrators
     * time to renew.
     *
     * @return bool True if currently within the grace period.
     */
    public function is_in_grace_period(): bool {
        $cached = $this->get_cached_license();
        if (!$cached) {
            return false;
        }

        if ($cached->status !== self::STATUS_EXPIRED) {
            return false;
        }

        // Check the graceuntil field first.
        if (!empty($cached->graceuntil)) {
            return time() <= (int) $cached->graceuntil;
        }

        // Fall back to computing grace period from expiry date.
        if (empty($cached->expirydate)) {
            return false;
        }

        $graceend = (int) $cached->expirydate + self::GRACE_PERIOD_SECONDS;
        return time() <= $graceend;
    }

    /**
     * Verify the HMAC signature of a license server response.
     *
     * The signature is expected in the 'signature' field of the response
     * data. The HMAC is computed over the JSON-encoded response payload
     * (excluding the signature field) using SHA-256 with the license key
     * as the secret.
     *
     * @param array  $data       The decoded response data.
     * @param string $licensekey The license key used as the HMAC secret.
     * @return bool True if the signature is valid.
     */
    private function verify_response_signature(array $data, string $licensekey): bool {
        if (empty($data['signature'])) {
            // No signature provided -- reject.
            return false;
        }

        $signature = $data['signature'];
        unset($data['signature']);

        // Sort keys for consistent serialisation.
        ksort($data);
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $expectedsignature = hash_hmac('sha256', $payload, $licensekey);

        return hash_equals($expectedsignature, $signature);
    }

    /**
     * Cache the license status in the database.
     *
     * @param string      $licensekey The license key.
     * @param string      $domain     The site domain.
     * @param string      $status     The license status.
     * @param int|null    $expirydate The expiry timestamp, or null.
     * @param string      $signature  The server response signature.
     * @param int         $creditsremaining Remaining credits.
     * @param int         $creditstotal Total credits.
     * @param string|null $aiconfig JSON AI config string.
     * @return void
     */
    private function cache_license_status(
        string $licensekey,
        string $domain,
        string $status,
        ?int $expirydate,
        string $signature,
        int $creditsremaining = 0,
        int $creditstotal = 0,
        ?string $aiconfig = null
    ): void {
        global $DB;

        $now = time();

        // Calculate grace period end if expired.
        $graceuntil = null;
        if ($status === self::STATUS_EXPIRED && !empty($expirydate)) {
            $graceuntil = $expirydate + self::GRACE_PERIOD_SECONDS;
        }

        $existing = $DB->get_record('local_aceengine_license', ['licensekey' => $licensekey]);

        $record = new \stdClass();
        $record->licensekey = $licensekey;
        $record->domain = $domain;
        $record->status = $status;
        $record->expirydate = $expirydate;
        $record->graceuntil = $graceuntil;
        $record->lastcheck = $now;
        $record->response_signature = $signature;
        $record->credits_remaining = $creditsremaining;
        $record->credits_total = $creditstotal;
        $record->ai_config = $aiconfig;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aceengine_license', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_aceengine_license', $record);
        }
    }

    /**
     * Get the cached license record from the database.
     *
     * @return \stdClass|false The cached license record, or false if none exists.
     */
    private function get_cached_license(): \stdClass|false {
        global $DB;

        $licensekey = get_config('local_aceengine', 'licensekey');
        if (empty($licensekey)) {
            return false;
        }

        return $DB->get_record('local_aceengine_license', ['licensekey' => $licensekey]);
    }

    /**
     * Get the number of grace period days remaining.
     *
     * @return int The number of days remaining in the grace period, or 0.
     */
    private function get_grace_days_remaining(): int {
        $cached = $this->get_cached_license();
        if (!$cached) {
            return 0;
        }

        $graceend = 0;
        if (!empty($cached->graceuntil)) {
            $graceend = (int) $cached->graceuntil;
        } else if (!empty($cached->expirydate)) {
            $graceend = (int) $cached->expirydate + self::GRACE_PERIOD_SECONDS;
        }

        if ($graceend <= 0) {
            return 0;
        }

        $remaining = $graceend - time();
        if ($remaining <= 0) {
            return 0;
        }

        return (int) ceil($remaining / DAYSECS);
    }
}
