<?php
/**
 * Interactive setup script for configuring the NAS fulfillment poller.
 * Guides users through collecting required configuration and generates poller.php config.
 *
 * Usage: php tools/poller_setup.php
 * This script is interactive and runs on the user's local machine, not on the shared host.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "\n";
echo "====================================\n";
echo "  NAS Fulfillment Poller Setup\n";
echo "====================================\n\n";

echo "This script will help you configure the poller that manages photo fulfillment\n";
echo "from your home NAS. You'll need:\n";
echo "  • The gallery server URL\n";
echo "  • Poller token (from the setup wizard)\n";
echo "  • Your NAS device's MAC address\n";
echo "  • Your NAS's broadcast address\n\n";

// Get server URL
while (true) {
    echo "Gallery server URL (e.g., https://mygallery.com): ";
    $server = trim(fgets(STDIN));

    if (empty($server)) {
        echo "  Error: URL is required.\n\n";
        continue;
    }

    if (!filter_var($server, FILTER_VALIDATE_URL)) {
        echo "  Error: Must be a valid URL (https://...)\n\n";
        continue;
    }

    $server = rtrim($server, '/');
    break;
}

echo "\n";

// Get poller token
while (true) {
    echo "Poller token (from Setup Wizard - Remote NAS step): ";
    $token = trim(fgets(STDIN));

    if (empty($token)) {
        echo "  Error: Token is required.\n\n";
        continue;
    }

    if (strlen($token) < 40) {
        echo "  Error: Token looks too short. Did you copy it correctly?\n\n";
        continue;
    }

    break;
}

echo "\n";

// Get NAS MAC address
while (true) {
    echo "NAS MAC address (e.g., 00:11:22:33:44:55): ";
    $mac = trim(strtoupper(fgets(STDIN)));

    if (empty($mac)) {
        echo "  Error: MAC address is required.\n\n";
        continue;
    }

    // Validate MAC format
    if (!preg_match('/^([0-9A-F]{2}:){5}([0-9A-F]{2})$/', $mac)) {
        echo "  Error: Invalid MAC format. Use format like 00:11:22:33:44:55\n\n";
        continue;
    }

    break;
}

echo "\n";

// Get broadcast address
while (true) {
    echo "NAS broadcast address (e.g., 192.168.1.255): ";
    $broadcast = trim(fgets(STDIN));

    if (empty($broadcast)) {
        echo "  Error: Broadcast address is required.\n\n";
        continue;
    }

    if (!filter_var($broadcast, FILTER_VALIDATE_IP)) {
        echo "  Error: Invalid IP address.\n\n";
        continue;
    }

    break;
}

echo "\n";

// Summary and confirmation
echo "Configuration summary:\n";
echo "  Server URL:        " . htmlspecialchars($server) . "\n";
echo "  Poller token:      " . substr($token, 0, 10) . "...\n";
echo "  NAS MAC address:   " . htmlspecialchars($mac) . "\n";
echo "  Broadcast address: " . htmlspecialchars($broadcast) . "\n\n";

echo "Continue? (y/n): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'y') {
    echo "Setup cancelled.\n";
    exit(0);
}

echo "\n";

// Generate config PHP code
$configCode = <<<PHP
<?php
/**
 * Configuration for NAS fulfillment poller.
 *
 * This script runs on your always-on home machine and polls the gallery server
 * for photo fulfillment jobs. When a customer downloads photos, this script:
 * 1. Receives notification from the server
 * 2. Sends Wake-on-LAN magic packet to your NAS
 * 3. Reports back when files are ready
 */

// Gallery server configuration
define('SERVER_URL', '{$server}');
define('POLLER_TOKEN', '{$token}');

// NAS device configuration
define('NAS_MAC_ADDRESS', '{$mac}');
define('NAS_BROADCAST', '{$broadcast}');

// Polling interval (seconds between checks)
define('POLL_INTERVAL', 60);

// Maximum time to wait for NAS to wake up (seconds)
define('NAS_WAKE_TIMEOUT', 120);

// Log file path (relative to this script's directory)
define('LOG_FILE', __DIR__ . '/poller.log');

// Enable debug logging (set to false in production)
define('DEBUG', false);
PHP;

// Suggest installation location
$scriptDir = dirname(__FILE__);
$suggestedPath = $scriptDir . '/poller_config.php';

echo "Generated configuration. You have two options:\n\n";

echo "Option 1: Save to file\n";
echo "  Write to: {$suggestedPath}\n";
echo "  Then run: php {$scriptDir}/poller.php\n\n";

echo "Save to file? (y/n): ";
$save = trim(fgets(STDIN));

if (strtolower($save) === 'y') {
    // Check if file exists
    if (file_exists($suggestedPath)) {
        echo "File already exists. Overwrite? (y/n): ";
        $overwrite = trim(fgets(STDIN));

        if (strtolower($overwrite) !== 'y') {
            echo "Not saved. Configuration:\n\n";
            echo $configCode;
            exit(0);
        }
    }

    // Write the config file
    if (file_put_contents($suggestedPath, $configCode) === false) {
        echo "Error: Could not write to {$suggestedPath}\n";
        echo "Check that the directory is writable.\n\n";
        echo "Configuration:\n\n";
        echo $configCode;
        exit(1);
    }

    echo "✓ Configuration saved to {$suggestedPath}\n\n";

    // Show next steps
    echo "Next steps:\n";
    echo "  1. Verify the poller.php script exists: ls -la {$scriptDir}/poller.php\n";
    echo "  2. Test the connection: php {$scriptDir}/poller.php\n";
    echo "  3. Set up continuous running (one of the following):\n";
    echo "     • Run in a screen session: screen -S poller -d php {$scriptDir}/poller.php\n";
    echo "     • Or create a systemd service (see docs/NAS-FULFILLMENT.md)\n";
    echo "     • Or add to cron: */5 * * * * php {$scriptDir}/poller.php\n\n";
} else {
    echo "Configuration:\n\n";
    echo $configCode;
    echo "\n\nSave this to {$suggestedPath} before running the poller.\n";
}

echo "Done.\n\n";
?>
