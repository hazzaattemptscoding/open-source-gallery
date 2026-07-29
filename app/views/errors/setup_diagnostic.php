<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Required</title>
    <link rel="stylesheet" href="/assets/css/podium-ink.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= e($config['site']['name'] ?? 'Photo Gallery') ?></h1>
            <p>Setup Required</p>
        </div>

        <div class="section">
            <?php if ($bootstrapError === 'config_missing'): ?>
                <div class="status-box">
                    <h2>Configuration File Missing</h2>
                    <div class="error-message">
                        config/config.php not found
                    </div>
                    <p>The application needs a configuration file before it can run.</p>
                </div>

                <div class="step">
                    <h3><span class="step-number">1</span>Create config file</h3>
                    <p>Copy the example config and customize it:</p>
                    <div class="code-block">cp config/config.example.php config/config.php</div>
                </div>

                <div class="step">
                    <h3><span class="step-number">2</span>Edit with your database details</h3>
                    <p>Open config/config.php and fill in:</p>
                    <div class="code-block">
'db' => [<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'host' => 'localhost',&nbsp;&nbsp;&nbsp;&nbsp;// Your database host<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'port' => 3306,<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'name' => 'photo_gallery',&nbsp;&nbsp;&nbsp;&nbsp;// Database name<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'user' => 'gallery',&nbsp;&nbsp;&nbsp;&nbsp;// Database user<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'pass' => 'your_password',&nbsp;&nbsp;&nbsp;&nbsp;// Database password<br/>
],
                    </div>
                </div>

                <div class="step">
                    <h3><span class="step-number">3</span>Choose your setup method</h3>
                    <p style="margin-bottom: 16px;">Run one of these commands:</p>
                    <div style="display: grid; gap: 12px;">
                        <div>
                            <p style="font-weight: 500; margin-bottom: 8px;">Easiest (if Docker is installed):</p>
                            <div class="code-block">docker-compose up</div>
                        </div>
                        <div>
                            <p style="font-weight: 500; margin-bottom: 8px;">Or use the installer:</p>
                            <div class="code-block">php install.php</div>
                        </div>
                    </div>
                </div>

                <p>
                    <a href="../INSTALL.md" class="link">Read the full installation guide</a> for detailed setup instructions.
                </p>

            <?php elseif ($bootstrapError === 'db_connection_failed'): ?>
                <div class="status-box">
                    <h2>Database Connection Failed</h2>
                    <div class="error-message">
                        Could not connect to the database.<br/>
                        Check your config/config.php database settings.
                    </div>
                    <p>The configuration file exists, but the database is not accessible.</p>
                </div>

                <div class="step">
                    <h3><span class="step-number">1</span>Verify database is running</h3>
                    <p>Make sure your MySQL/MariaDB server is running and accessible from this machine.</p>
                </div>

                <div class="step">
                    <h3><span class="step-number">2</span>Check database credentials</h3>
                    <p>In config/config.php, verify:</p>
                    <ul style="margin-left: 20px; color: #787774;">
                        <li><code style="background: #F7F6F3; padding: 2px 4px;">host</code> — IP address or hostname</li>
                        <li><code style="background: #F7F6F3; padding: 2px 4px;">port</code> — Usually 3306</li>
                        <li><code style="background: #F7F6F3; padding: 2px 4px;">name</code> — Database name (e.g., photo_gallery)</li>
                        <li><code style="background: #F7F6F3; padding: 2px 4px;">user</code> — Database user</li>
                        <li><code style="background: #F7F6F3; padding: 2px 4px;">pass</code> — Database password</li>
                    </ul>
                </div>

                <div class="step">
                    <h3><span class="step-number">3</span>Test the connection</h3>
                    <p>Try connecting manually:</p>
                    <div class="code-block">mysql -h localhost -u gallery -p photo_gallery</div>
                    <p style="margin-top: 12px; color: #787774;">If this fails, your database settings need adjustment.</p>
                </div>

                <div class="step">
                    <h3><span class="step-number">4</span>Create the database</h3>
                    <p>If the database doesn't exist yet, the installer will create it:</p>
                    <div class="code-block">php install.php</div>
                </div>

                <p>
                    <a href="../INSTALL.md" class="link">See the installation guide</a> for more detailed troubleshooting.
                </p>

            <?php endif; ?>
        </div>

        <hr class="divider">

        <div class="section">
            <h2 style="margin-bottom: 16px;">Quick Reference</h2>
            <p style="margin-bottom: 16px; color: #787774;">
                The most common setup paths:
            </p>

            <div style="display: grid; gap: 16px;">
                <div style="border: 1px solid #EAEAEA; border-radius: 8px; padding: 20px;">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">Docker (Fastest)</h3>
                    <div class="code-block" style="margin: 0;">docker-compose up</div>
                    <p style="margin-top: 12px; font-size: 13px; color: #787774;">
                        Includes database, automatically configured
                    </p>
                </div>

                <div style="border: 1px solid #EAEAEA; border-radius: 8px; padding: 20px;">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">Interactive Installer</h3>
                    <div class="code-block" style="margin: 0;">php install.php</div>
                    <p style="margin-top: 12px; font-size: 13px; color: #787774;">
                        Works on any environment, asks for database details
                    </p>
                </div>

                <div style="border: 1px solid #EAEAEA; border-radius: 8px; padding: 20px;">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">Verify Your Setup</h3>
                    <div class="code-block" style="margin: 0;">php verify-setup.php</div>
                    <p style="margin-top: 12px; font-size: 13px; color: #787774;">
                        Check environment, permissions, database readiness
                    </p>
                </div>
            </div>
        </div>

        <hr class="divider">

        <div style="color: #787774; font-size: 13px;">
            <p>
                PowerMedia Gallery is an open-source, self-hosted photo gallery for sports photographers.
                <br/>
                <a href="https://github.com/hazzaattemptscoding/open-source-gallery" class="link">View on GitHub</a> •
                <a href="../docs/architecture.md" class="link">Architecture docs</a>
            </p>
        </div>
    </div>
</body>
</html>
