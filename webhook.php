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

/**
 * Webhook endpoint for real-time license/credit updates from the License Server.
 *
 * The License Server calls this endpoint whenever credits or license status
 * change, so Moodle always has up-to-date data without waiting for cron.
 *
 * Security: Verified via HMAC-SHA256 signature using the license key as secret.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    die;
}

// Parse the JSON body.
$rawbody = file_get_contents('php://input');
$data = json_decode($rawbody, true);

if (!is_array($data) || empty($data['license_key'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    die;
}

// Verify the signature using the license key as HMAC secret.
$licensekey = get_config('local_ace', 'licensekey');
if (empty($licensekey) || $data['license_key'] !== $licensekey) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid license key']);
    die;
}

// Verify HMAC signature.
$signature = $data['signature'] ?? '';
unset($data['signature']);
ksort($data);
$expectedsig = hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $licensekey);

if (!hash_equals($expectedsig, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    die;
}

// Update the local license cache.
$now = time();
$record = $DB->get_record('local_ace_license', ['licensekey' => $licensekey]);

$updatedata = [];
if (isset($data['credits_remaining'])) {
    $updatedata['credits_remaining'] = (int) $data['credits_remaining'];
}
if (isset($data['credits_total'])) {
    $updatedata['credits_total'] = (int) $data['credits_total'];
}
if (isset($data['status'])) {
    // Map server statuses to Moodle-recognized statuses.
    // The server may send 'revoked' which is not a recognized Moodle status constant.
    $statusmap = ['revoked' => 'invalid', 'suspended' => 'invalid'];
    $updatedata['status'] = $statusmap[$data['status']] ?? $data['status'];
}
if (isset($data['expiry_date'])) {
    $updatedata['expirydate'] = (int) $data['expiry_date'];
}

if ($record && !empty($updatedata)) {
    $updatedata['timemodified'] = $now;
    $updatedata['lastcheck'] = $now;
    $updatedata['id'] = $record->id;
    $DB->update_record('local_ace_license', (object) $updatedata);
} else if (!$record && !empty($updatedata)) {
    $updatedata['licensekey'] = $licensekey;
    $updatedata['domain'] = parse_url($CFG->wwwroot, PHP_URL_HOST);
    $updatedata['timecreated'] = $now;
    $updatedata['timemodified'] = $now;
    $updatedata['lastcheck'] = $now;
    $DB->insert_record('local_ace_license', (object) $updatedata);
}

// Purge the MUC cache so Pro features re-check license status immediately.
if (class_exists('\local_ace_pro\pro_manager')) {
    \local_ace_pro\pro_manager::purge_cache();
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'updated' => array_keys($updatedata),
    'timestamp' => $now,
]);
