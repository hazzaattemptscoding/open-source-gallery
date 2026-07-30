<?php
$pageTitle = 'Emails';
$currentPage = 'emails';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="emails-container">
  <div style="margin-bottom: 3rem;">
    <h2>Email Queue</h2>
    <p>Pending: <?= $stats['pending'] ?? 0 ?> | Sent: <?= $stats['sent'] ?? 0 ?> | Failed: <?= $stats['failed'] ?? 0 ?></p>
  </div>

  <div style="margin-bottom: 3rem;">
    <h3>Recent Emails</h3>
    <table class="queue-table">
      <thead>
        <tr>
          <th>Recipient</th>
          <th>Subject</th>
          <th>Status</th>
          <th>Retries</th>
          <th>Sent</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($queue as $email): ?>
          <tr>
            <td><?= e($email['recipient_email']) ?></td>
            <td><?= e(substr($email['subject'], 0, 50)) ?></td>
            <td><span class="badge badge-<?= e($email['status']) ?>"><?= e(ucfirst($email['status'])) ?></span></td>
            <td><?= (int)$email['retry_count'] ?></td>
            <td><?= $email['sent_at'] ? e(date('M d H:i', strtotime($email['sent_at']))) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-bottom: 3rem;">
    <h3>Email Templates</h3>
    <?php foreach ($templates as $template): ?>
      <div style="margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #eee; border-radius: 4px;">
        <h4><?= e($template['display_name']) ?></h4>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="action" value="update_template">
          <input type="hidden" name="template_id" value="<?= (int)$template['id'] ?>">
          
          <div style="margin-bottom: 1rem;">
            <label>Subject Template:</label>
            <input type="text" name="subject_template" value="<?= e($template['subject_template']) ?>" style="width: 100%; padding: 0.5rem;">
          </div>

          <div style="margin-bottom: 1rem;">
            <label>HTML Template:</label>
            <textarea name="body_html_template" style="width: 100%; height: 200px; padding: 0.5rem;"><?= e($template['body_html_template']) ?></textarea>
          </div>

          <div style="margin-bottom: 1rem;">
            <label><input type="checkbox" name="enabled" <?= $template['enabled'] ? 'checked' : '' ?>> Enabled</label>
          </div>

          <button type="submit">Update Template</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
