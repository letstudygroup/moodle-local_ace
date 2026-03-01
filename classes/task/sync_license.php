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

namespace local_aceengine\task;

use core\task\scheduled_task;
/**
 * Scheduled task to verify the ACE Pro license with the license server.
 *
 * Contacts the configured license server to validate the current license key
 * and updates the local license status cache accordingly.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_license extends scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_sync_license', 'local_aceengine');
    }

    /**
     * Execute the scheduled task.
     *
     * Retrieves the configured license key and server URL, sends a verification
     * request to the license server, and updates the local license cache with
     * the response. Handles network failures gracefully with a grace period.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        $licensekey = get_config('local_aceengine', 'licensekey');
        if (empty($licensekey)) {
            mtrace('No license key configured. Skipping license sync.');
            return;
        }

        $licenseserver = \local_aceengine\licensing\license_manager::LICENSE_SERVER_URL;

        $now = time();
        $domain = parse_url($CFG->wwwroot, PHP_URL_HOST);

        mtrace("Verifying license key with server: {$licenseserver}");

        // Attempt to verify the license with the remote server.
        $response = self::call_license_server($licenseserver, $licensekey, $domain);

        // Get or create the local license record.
        $license = $DB->get_record('local_aceengine_license', ['licensekey' => $licensekey]);

        if ($response === null) {
            // Network or server error - apply grace period logic.
            mtrace('License server unreachable. Applying grace period logic.');
            self::handle_unreachable_server($license, $licensekey, $domain, $now);
            return;
        }

        // Extract credit and AI config data from enhanced response.
        $creditsremaining = (int) ($response['credits_remaining'] ?? 0);
        $creditstotal = (int) ($response['credits_total'] ?? 0);
        $aiconfig = isset($response['ai_config']) ? json_encode($response['ai_config']) : null;

        if ($license) {
            $license->status = $response['status'];
            $license->expirydate = $response['expirydate'] ?? null;
            $license->graceuntil = null;
            $license->lastcheck = $now;
            $license->response_signature = $response['signature'] ?? null;
            $license->credits_remaining = $creditsremaining;
            $license->credits_total = $creditstotal;
            $license->ai_config = $aiconfig;
            $license->timemodified = $now;
            $DB->update_record('local_aceengine_license', $license);
        } else {
            $DB->insert_record('local_aceengine_license', (object) [
                'licensekey' => $licensekey,
                'domain' => $domain,
                'status' => $response['status'],
                'expirydate' => $response['expirydate'] ?? null,
                'graceuntil' => null,
                'lastcheck' => $now,
                'response_signature' => $response['signature'] ?? null,
                'credits_remaining' => $creditsremaining,
                'credits_total' => $creditstotal,
                'ai_config' => $aiconfig,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        mtrace("License verification complete. Status: {$response['status']}");
        if ($creditsremaining > 0 || $creditstotal > 0) {
            mtrace("Credits: {$creditsremaining} / {$creditstotal}");
        }
        if ($aiconfig) {
            mtrace('AI configuration cached from license server.');
        }
    }

    /**
     * Send a verification request to the license server.
     *
     * @param string $serverurl The license server URL.
     * @param string $licensekey The license key to verify.
     * @param string $domain The current Moodle domain.
     * @return array|null The parsed response data, or null on failure.
     */
    private static function call_license_server(string $serverurl, string $licensekey, string $domain): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = rtrim($serverurl, '/') . '/verify';

        $postdata = [
            'license_key' => $licensekey,
            'domain' => $domain,
            'product' => 'local_aceengine',
        ];

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_TIMEOUT' => 30,
        ]);
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');

        $response = $curl->post($url, json_encode($postdata));

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200 || empty($response)) {
            mtrace("License server returned HTTP {$httpcode}.");
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['status'])) {
            mtrace('Invalid response from license server.');
            return null;
        }

        return $decoded;
    }

    /**
     * Handle the case where the license server is unreachable.
     *
     * If a license record exists, applies a 7-day grace period from the last
     * successful check. If the grace period has elapsed, the license is marked
     * as expired.
     *
     * @param object|false $license The existing license record, or false if none exists.
     * @param string $licensekey The license key.
     * @param string $domain The current Moodle domain.
     * @param int $now The current timestamp.
     * @return void
     */
    private static function handle_unreachable_server(
        object|false $license,
        string $licensekey,
        string $domain,
        int $now
    ): void {
        global $DB;

        $graceperiod = 7 * DAYSECS; // 7-day grace period.

        if (!$license) {
            // No previous license record; cannot grant grace period.
            mtrace('No existing license record found. License features disabled.');
            $DB->insert_record('local_aceengine_license', (object) [
                'licensekey' => $licensekey,
                'domain' => $domain,
                'status' => 'inactive',
                'expirydate' => null,
                'graceuntil' => null,
                'lastcheck' => $now,
                'response_signature' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            return;
        }

        // If previously valid, set or check grace period.
        if ($license->status === 'valid' || $license->status === 'grace') {
            if (empty($license->graceuntil)) {
                // Start grace period from now.
                $license->graceuntil = $now + $graceperiod;
                $license->status = 'grace';
                mtrace('Starting 7-day grace period.');
            } else if ($now > $license->graceuntil) {
                // Grace period has expired.
                $license->status = 'expired';
                mtrace('Grace period has expired. License marked as expired.');
            } else {
                $remaining = ceil(($license->graceuntil - $now) / DAYSECS);
                mtrace("Grace period active: {$remaining} day(s) remaining.");
            }

            $license->lastcheck = $now;
            $license->timemodified = $now;
            $DB->update_record('local_aceengine_license', $license);
        }
    }
}
