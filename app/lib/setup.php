<?php
declare(strict_types=1);

/**
 * Setup wizard state management. Tracks which setup steps have been completed
 * and which were skipped. Persists in settings table so checklist survives login.
 */

function is_setup_complete(PDO $pdo): bool {
    $required = ['admin_account', 'business_details'];
    $completed = get_completed_steps($pdo);

    foreach ($required as $step) {
        if (!in_array($step, $completed, true)) {
            return false;
        }
    }

    return true;
}

function get_completed_steps(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute(['setup_completed_steps']);
    $json = (string)$stmt->fetchColumn();

    return $json ? json_decode($json, true) : [];
}

function get_skipped_steps(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $stmt->execute(['setup_skipped_steps']);
    $json = (string)$stmt->fetchColumn();

    return $json ? json_decode($json, true) : [];
}

function mark_step_complete(PDO $pdo, string $step): void {
    $completed = get_completed_steps($pdo);
    if (!in_array($step, $completed, true)) {
        $completed[] = $step;
        save_steps($pdo, 'setup_completed_steps', $completed);
    }

    // Remove from skipped if it was skipped before
    $skipped = get_skipped_steps($pdo);
    if (($key = array_search($step, $skipped, true)) !== false) {
        unset($skipped[$key]);
        save_steps($pdo, 'setup_skipped_steps', array_values($skipped));
    }
}

function mark_step_skipped(PDO $pdo, string $step): void {
    $skipped = get_skipped_steps($pdo);
    if (!in_array($step, $skipped, true)) {
        $skipped[] = $step;
        save_steps($pdo, 'setup_skipped_steps', $skipped);
    }

    // Remove from completed if it was completed before
    $completed = get_completed_steps($pdo);
    if (($key = array_search($step, $completed, true)) !== false) {
        unset($completed[$key]);
        save_steps($pdo, 'setup_completed_steps', array_values($completed));
    }
}

function get_incomplete_steps(PDO $pdo): array {
    $allSteps = ['admin_account', 'business_details', 'email_setup', 'stripe_keys', 'storage_mode', 'admin_mode'];
    $completed = get_completed_steps($pdo);
    $skipped = get_skipped_steps($pdo);

    return array_filter($allSteps, function ($step) use ($completed, $skipped) {
        return !in_array($step, $completed, true) && !in_array($step, $skipped, true);
    });
}

function get_setup_checklist(PDO $pdo): array {
    $completed = get_completed_steps($pdo);
    $skipped = get_skipped_steps($pdo);

    $steps = [
        'admin_account' => [
            'label' => 'Create admin account',
            'required' => true,
            'url' => '/admin/setup?step=admin_account',
        ],
        'business_details' => [
            'label' => 'Business details',
            'required' => true,
            'url' => '/admin/setup?step=business_details',
        ],
        'email_setup' => [
            'label' => 'Email setup (for order delivery)',
            'required' => false,
            'url' => '/admin/setup?step=email_setup',
        ],
        'stripe_keys' => [
            'label' => 'Stripe API keys (for payments)',
            'required' => false,
            'url' => '/admin/setup?step=stripe_keys',
        ],
        'storage_mode' => [
            'label' => 'File storage configuration',
            'required' => false,
            'url' => '/admin/setup?step=storage_mode',
        ],
        'admin_mode' => [
            'label' => 'Admin panel location',
            'required' => false,
            'url' => '/admin/setup?step=admin_mode',
        ],
    ];

    return array_map(function ($key, $step) use ($completed, $skipped) {
        $step['key'] = $key;
        $step['status'] = in_array($key, $completed, true) ? 'complete'
                        : (in_array($key, $skipped, true) ? 'skipped' : 'pending');
        return $step;
    }, array_keys($steps), $steps);
}

function save_steps(PDO $pdo, string $key, array $steps): void {
    $upsert = db_upsert_sql($pdo, 'settings', ['skey' => $key, 'svalue' => json_encode($steps)], 'skey');
    $stmt = $pdo->prepare($upsert['sql']);
    $stmt->execute($upsert['values']);
}

/**
 * Generate a secure poller token for fulfillment API (NAS mode).
 * Returns the plain token (save to config), stores hash in database.
 */
function generate_poller_token(PDO $pdo): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $upsert = db_upsert_sql($pdo, 'settings', ['skey' => 'fulfillment_poller_token_hash', 'svalue' => $hash], 'skey');
    $stmt = $pdo->prepare($upsert['sql']);
    $stmt->execute($upsert['values']);

    return $token;
}

function regenerate_poller_token(PDO $pdo): string {
    return generate_poller_token($pdo); // Same implementation
}
