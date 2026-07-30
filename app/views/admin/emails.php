<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Management: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.emails-container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
.queue-table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
.queue-table th, .queue-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
.queue-table th { background: #f9f9f9; font-weight: 600; }
.badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.85rem; }
.badge-pending { background: #fff8e1; color: #f57f17; }
.badge-sent { background: #c8e6c9; color: #2e7d32; }
.badge-failed { background: #ffebee; color: #c62828; }
</style>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title"><?= e($siteName) ?></a>
</header>

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

</body>
</html>
