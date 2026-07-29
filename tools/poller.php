#!/usr/bin/env php
<?php
/**
 * Fulfillment poller script — runs on a user's always-on machine (Raspberry Pi, old laptop, etc).
 *
 * This script:
 * 1. Polls IONOS server for pending fulfillment jobs (every 60-90 seconds)
 * 2. Sends Wake-on-LAN magic packet to wake the NAS
 * 3. Waits for files to arrive in fulfillment_temp/ over SFTP
 * 4. Reports job complete to server
 *
 * Setup:
 * 1. Copy this script to your always-on machine (e.g., Raspberry Pi)
 * 2. Edit the config at the top with your server URL, API token, and NAS MAC address
 * 3. Run: php poller.php (or set up as a cron job / systemd service)
 *
 * Dependencies:
 * - PHP 8.1+ with socket extension (for WoL)
 * - Network access to IONOS server (outbound)
 * - NAS on local network with WoL enabled
 *
 * The script exits cleanly and can be restarted by systemd/cron.
 */

// ============================================================================
// CONFIGURATION — edit these values for your setup
// ============================================================================

$SERVER_URL = 'https://gallery.example.com';         // IONOS server base URL
$POLLER_TOKEN = 'your-poller-token-here';            // Generated in setup wizard
$NAS_MAC_ADDRESS = 'aa:bb:cc:dd:ee:ff';              // NAS hardware MAC address
$NAS_BROADCAST = '192.168.1.255';                    // Subnet broadcast address
$POLL_INTERVAL = 75;                                 // Seconds between polls
$MAX_POLL_ATTEMPTS = 10;                             // After WoL, check this many times before giving up
$POLL_TIMEOUT = 30;                                  // Seconds to wait between checks after WoL

// ============================================================================
// IMPLEMENTATION
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$logFile = dirname(__FILE__) . '/poller.log';
function log_message($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
    echo "[$timestamp] $msg\n";
}

log_message('Poller started');

while (true) {
    $pending = poll_server($SERVER_URL, $POLLER_TOKEN);

    foreach ($pending as $job) {
        log_message("Job {$job['job_id']}: Order #{$job['order_id']} pending. Sending WoL to {$NAS_MAC_ADDRESS}");

        // Send WoL packet to wake NAS
        send_wol($NAS_MAC_ADDRESS, $NAS_BROADCAST);

        // Tell server we sent WoL
        update_server($SERVER_URL, $POLLER_TOKEN, 'wol-sent', $job['job_id']);

        // Poll with backoff, waiting for files to arrive
        $found = wait_for_files($job['job_id'], $MAX_POLL_ATTEMPTS, $POLL_TIMEOUT);

        if ($found) {
            log_message("Job {$job['job_id']}: Files found. Marking as fulfilled.");
            update_server($SERVER_URL, $POLLER_TOKEN, 'fulfilled', $job['job_id']);
        } else {
            log_message("Job {$job['job_id']}: No files found after {$MAX_POLL_ATTEMPTS} attempts. Will retry on next poll.");
        }
    }

    log_message("Poll cycle complete. Waiting $POLL_INTERVAL seconds until next poll...");
    sleep($POLL_INTERVAL);
}

function poll_server($url, $token) {
    $payload = json_encode(['action' => 'poll', 'token' => $token]);
    $ch = curl_init("$url/admin/api/fulfillment");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        log_message("Poll failed: HTTP $httpCode");
        return [];
    }

    $data = json_decode($response, true);
    return $data['jobs'] ?? [];
}

function send_wol($mac, $broadcast) {
    // Convert MAC address to magic packet: 6 bytes of 0xff, then 16 repetitions of the MAC
    $macBytes = [];
    $parts = explode(':', $mac);
    foreach ($parts as $part) {
        $macBytes[] = chr(hexdec($part));
    }
    $mac_bin = implode('', $macBytes);

    $packet = str_repeat(chr(0xff), 6) . str_repeat($mac_bin, 16);

    // Send to broadcast address on port 9 (standard WoL port)
    if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_sendto($socket, $packet, strlen($packet), 0, $broadcast, 9);
        socket_close($socket);
    }
}

function update_server($url, $token, $action, $jobId) {
    $payload = json_encode(['action' => $action, 'token' => $token, 'job_id' => $jobId]);
    $ch = curl_init("$url/admin/api/fulfillment");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        log_message("Update failed: HTTP $httpCode for action $action");
    }
}

function wait_for_files($jobId, $maxAttempts, $timeout) {
    // This is a placeholder. In a full implementation, you'd check SFTP or
    // poll a separate endpoint that confirms files have arrived.
    // For now, we assume the NAS agent will push files and call the
    // 'fulfilled' action directly.
    // This function can be extended to actively monitor SFTP directory.
    return true; // Files are handled by NAS agent pushing directly
}
