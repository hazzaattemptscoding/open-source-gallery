<?php declare(strict_types=1); ?>
<h2 class="step-title">Setup Complete!</h2>
<p class="step-description">Your gallery is ready. You can customize settings anytime from the admin panel.</p>

<div class="summary-grid">
    <div class="summary-item">
        <div class="summary-item-label">Admin Account</div>
        <div class="summary-item-value">✓ Created</div>
    </div>

    <div class="summary-item">
        <div class="summary-item-label">Business Details</div>
        <div class="summary-item-value">✓ Configured</div>
    </div>

    <?php if (in_array('email_setup', $data['completed'] ?? [])): ?>
        <div class="summary-item">
            <div class="summary-item-label">Email Delivery</div>
            <div class="summary-item-value">✓ Configured</div>
        </div>
    <?php else: ?>
        <div class="summary-item">
            <div class="summary-item-label">Email Delivery</div>
            <div class="summary-item-value">⊘ Skipped (no customer emails)</div>
        </div>
    <?php endif; ?>

    <?php if (in_array('stripe_keys', $data['completed'] ?? [])): ?>
        <div class="summary-item">
            <div class="summary-item-label">Stripe Payments</div>
            <div class="summary-item-value">✓ Configured</div>
        </div>
    <?php else: ?>
        <div class="summary-item">
            <div class="summary-item-label">Stripe Payments</div>
            <div class="summary-item-value">⊘ Skipped (no checkout)</div>
        </div>
    <?php endif; ?>
</div>

<div class="wizard-next-steps">
    <strong>Next steps:</strong>
    <ul>
        <li>Create your first event and upload photos</li>
        <li>Customize watermark settings</li>
        <li>Set up your payment processing (if skipped)</li>
        <li>Configure email delivery (if skipped)</li>
    </ul>
</div>
