<?php
$pageTitle = 'Setup Required';
$includeAdminStyles = true;
require_once __DIR__ . '/../admin/partials/minimal_header.php';
?>
    <div class="diagnostic-container">
        <div class="diagnostic-header">
            <h1><?= e($config['site']['name'] ?? 'Photo Gallery') ?></h1>
            <p>Setup Required</p>
        </div>

        <div class="diagnostic-section">
            <?php if ($bootstrapError === 'config_missing'): ?>
                <div class="status-box">
                    <h2>Configuration File Missing</h2>
                    <div class="diagnostic-error-message">
                        config/config.php not found
                    </div>
                    <p>The application needs a configuration file before it can run.</p>
                </div>

                <div class="diagnostic-step">
                    <h3><span class="step-number">1</span>Create config file</h3>
                    <p>Copy the example config and customize it:</p>
                    <div class="code-block">cp config/config.example.php config/config.php</div>
                </div>

                <div class="diagnostic-step">
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

                <div class="diagnostic-step">
                    <h3><span class="step-number">3</span>Choose your setup method</h3>
                    <p>Run one of these commands:</p>
                    <div class="diagnostic-substeps">
                        <div>
                            <p class="diagnostic-substep-label">Easiest (if Docker is installed):</p>
                            <div class="code-block">docker-compose up</div>
                        </div>
                        <div>
                            <p class="diagnostic-substep-label">Or use the installer:</p>
                            <div class="code-block">php install.php</div>
                        </div>
                    </div>
                </div>

                <p>
                    <a href="../INSTALL.md" class="diagnostic-link">Read the full installation guide</a> for detailed setup instructions.
                </p>

            <?php elseif ($bootstrapError === 'db_connection_failed'): ?>
                <div class="status-box">
                    <h2>Database Connection Failed</h2>
                    <div class="diagnostic-error-message">
                        Could not connect to the database.<br/>
                        Check your config/config.php database settings.
                    </div>
                    <p>The configuration file exists, but the database is not accessible.</p>
                </div>

                <div class="diagnostic-step">
                    <h3><span class="step-number">1</span>Verify database is running</h3>
                    <p>Make sure your MySQL/MariaDB server is running and accessible from this machine.</p>
                </div>

                <div class="diagnostic-step">
                    <h3><span class="step-number">2</span>Check database credentials</h3>
                    <p>In config/config.php, verify:</p>
                    <ul>
                        <li><code>host</code> — IP address or hostname</li>
                        <li><code>port</code> — Usually 3306</li>
                        <li><code>name</code> — Database name (e.g., photo_gallery)</li>
                        <li><code>user</code> — Database user</li>
                        <li><code>pass</code> — Database password</li>
                    </ul>
                </div>

                <div class="diagnostic-step">
                    <h3><span class="step-number">3</span>Test the connection</h3>
                    <p>Try connecting manually:</p>
                    <div class="code-block">mysql -h localhost -u gallery -p photo_gallery</div>
                    <p>If this fails, your database settings need adjustment.</p>
                </div>

                <div class="diagnostic-step">
                    <h3><span class="step-number">4</span>Create the database</h3>
                    <p>If the database doesn't exist yet, the installer will create it:</p>
                    <div class="code-block">php install.php</div>
                </div>

                <p>
                    <a href="../INSTALL.md" class="diagnostic-link">See the installation guide</a> for more detailed troubleshooting.
                </p>

            <?php endif; ?>
        </div>

        <hr class="divider">

        <div class="diagnostic-section">
            <h2>Quick Reference</h2>
            <p>
                The most common setup paths:
            </p>

            <div class="diagnostic-cards">
                <div class="diagnostic-card">
                    <h3>Docker (Fastest)</h3>
                    <div class="code-block">docker-compose up</div>
                    <p class="diagnostic-card-note">
                        Includes database, automatically configured
                    </p>
                </div>

                <div class="diagnostic-card">
                    <h3>Interactive Installer</h3>
                    <div class="code-block">php install.php</div>
                    <p class="diagnostic-card-note">
                        Works on any environment, asks for database details
                    </p>
                </div>

                <div class="diagnostic-card">
                    <h3>Verify Your Setup</h3>
                    <div class="code-block">php verify-setup.php</div>
                    <p class="diagnostic-card-note">
                        Check environment, permissions, database readiness
                    </p>
                </div>
            </div>
        </div>

        <hr class="divider">

        <div class="diagnostic-footer">
            <p>
                PowerMedia Gallery is an open-source, self-hosted photo gallery for sports photographers.
                <br/>
                <a href="https://github.com/hazzaattemptscoding/open-source-gallery" class="diagnostic-link">View on GitHub</a> •
                <a href="../docs/architecture.md" class="diagnostic-link">Architecture docs</a>
            </p>
        </div>
    </div>
<?php require_once __DIR__ . '/../admin/partials/minimal_footer.php'; ?>
