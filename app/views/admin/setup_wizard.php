<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Wizard — <?= htmlspecialchars($siteName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            color: #111;
            line-height: 1.6;
            min-height: 100vh;
        }

        .wizard-container {
            max-width: 720px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .wizard-header {
            text-align: center;
            margin-bottom: 64px;
        }

        .wizard-header h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        .wizard-header p {
            font-size: 16px;
            color: #666;
        }

        .wizard-progress {
            display: flex;
            gap: 8px;
            margin-bottom: 48px;
            justify-content: center;
        }

        .progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e0e0e0;
            transition: all 200ms ease-out;
        }

        .progress-dot.active {
            background: #111;
            width: 32px;
            border-radius: 4px;
        }

        .progress-dot.complete {
            background: #111;
        }

        .step-content {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 48px 40px;
            margin-bottom: 32px;
        }

        .step-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .step-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0d0d0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 160ms ease-out;
            background: #fff;
            color: #111;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #111;
            box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.05);
        }

        .help-text {
            font-size: 12px;
            color: #888;
            margin-top: 6px;
        }

        .provider-select {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .provider-option {
            padding: 12px 16px;
            border: 1px solid #d0d0d0;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            transition: all 160ms ease-out;
            background: #fff;
        }

        .provider-option:hover {
            border-color: #888;
        }

        .provider-option.active {
            background: #111;
            color: #fff;
            border-color: #111;
        }

        .mode-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .mode-option {
            padding: 20px;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 160ms ease-out;
        }

        .mode-option:hover {
            border-color: #888;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .mode-option.active {
            border-color: #111;
            background: #f9f9f8;
        }

        .mode-option input[type="radio"] {
            margin-right: 8px;
        }

        .mode-label {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .mode-description {
            font-size: 13px;
            color: #666;
        }

        .error {
            background: #fdebec;
            border: 1px solid #f5d0d2;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 24px;
            color: #9f2f2d;
            font-size: 13px;
        }

        .success {
            background: #edf3ec;
            border: 1px solid #d6e5d2;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 24px;
            color: #346538;
            font-size: 13px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 40px;
            justify-content: flex-end;
        }

        button, a.button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 160ms ease-out;
            text-decoration: none;
            display: inline-block;
        }

        .button-primary {
            background: #111;
            color: #fff;
        }

        .button-primary:hover {
            background: #333;
        }

        .button-primary:active {
            transform: scale(0.98);
        }

        .button-secondary {
            background: #f5f5f5;
            color: #111;
            border: 1px solid #d0d0d0;
        }

        .button-secondary:hover {
            background: #efefef;
        }

        .button-secondary:active {
            transform: scale(0.98);
        }

        .checklist {
            background: #f9f9f8;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 24px;
            margin-top: 40px;
        }

        .checklist-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }

        .checklist-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            font-size: 13px;
        }

        .checklist-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
        }

        .checklist-item.complete .checklist-icon {
            color: #4a8a5f;
        }

        .checklist-item.skipped .checklist-icon {
            color: #888;
        }

        .checklist-item.pending .checklist-icon {
            color: #d0d0d0;
        }

        .checklist-label {
            flex: 1;
        }

        .summary-grid {
            display: grid;
            gap: 20px;
            margin-top: 24px;
        }

        .summary-item {
            padding: 16px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }

        .summary-item-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #888;
            margin-bottom: 6px;
        }

        .summary-item-value {
            font-size: 15px;
            color: #111;
            word-break: break-word;
        }

        .poller-token {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            margin: 12px 0;
            border: 1px solid #d0d0d0;
        }

        .help-section {
            margin: 20px 0;
            padding: 16px;
            background: #f9f9f8;
            border-left: 3px solid #d0d0d0;
            border-radius: 4px;
        }

        .help-toggle {
            display: flex;
            gap: 8px;
            align-items: center;
            cursor: pointer;
            user-select: none;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .help-toggle:hover {
            color: #111;
        }

        .help-toggle-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            text-align: center;
            transition: transform 160ms ease-out;
        }

        .help-content {
            max-height: 500px;
            overflow: hidden;
            transition: max-height 160ms ease-out;
            font-size: 13px;
            line-height: 1.6;
        }

        .help-content.hidden {
            max-height: 0;
        }

        .help-content ul {
            margin: 8px 0 8px 20px;
            padding: 0;
        }

        .help-content li {
            margin: 4px 0;
        }

        .help-content code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }

        @media (max-width: 640px) {
            .wizard-container {
                padding: 24px 16px;
            }

            .step-content {
                padding: 32px 24px;
            }

            .wizard-header h1 {
                font-size: 24px;
            }

            .mode-options {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column-reverse;
            }

            button, a.button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
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
</body>
</html>
