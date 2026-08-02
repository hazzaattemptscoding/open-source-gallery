<?php
declare(strict_types=1);
$pageTitle = 'Setup Wizard';
$includeAdminStyles = true;
require_once __DIR__ . '/partials/minimal_header.php';
?>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1>Welcome to <?= htmlspecialchars($siteName) ?></h1>
            <p>Let's set up your gallery in just a few steps</p>
        </div>

        <div class="wizard-progress">
            <?php
            $steps = ['admin_account', 'business_details', 'email_setup', 'stripe_keys', 'storage_mode', 'admin_mode', 'summary'];
            $currentIndex = array_search($step, $steps);
            foreach ($steps as $i => $s) {
                $class = 'progress-dot';
                if ($i === $currentIndex) {
                    $class .= ' active';
                } elseif ($i < $currentIndex) {
                    $class .= ' complete';
                }
                echo "<div class='$class'></div>";
            }
            ?>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">✓ Step completed. Continue below.</div>
        <?php endif; ?>

        <form method="POST" class="step-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="step" value="<?= htmlspecialchars($step) ?>">

            <div class="step-content">
                <?php include __DIR__ . '/setup_wizard_' . $step . '.php'; ?>
            </div>

            <div class="button-group">
                <?php if ($step !== 'admin_account'): ?>
                    <a href="/admin/setup" class="button button-secondary">← Back</a>
                <?php endif; ?>

                <?php if ($step !== 'summary'): ?>
                    <button type="submit" class="button button-primary">Continue →</button>
                <?php else: ?>
                    <a href="/admin/login" class="button button-primary">Go to Admin →</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (isset($checklist)): ?>
            <div class="checklist">
                <div class="checklist-title">Setup Progress</div>
                <?php foreach ($checklist as $item): ?>
                    <div class="checklist-item <?= $item['status'] ?>">
                        <div class="checklist-icon">
                            <?php if ($item['status'] === 'complete'): ?>
                                ✓
                            <?php elseif ($item['status'] === 'skipped'): ?>
                                ⊘
                            <?php else: ?>
                                •
                            <?php endif; ?>
                        </div>
                        <div class="checklist-label"><?= htmlspecialchars($item['label']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<script src="/assets/js/admin-wizard.js" defer></script>
<?php require_once __DIR__ . '/partials/minimal_footer.php'; ?>
