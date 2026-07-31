<?php
/**
 * Email management: queue health, recent sends, and the editable templates.
 *
 * Everything below the queue summary is collapsed by default. The page's job on
 * open is to answer "is mail flowing?", which the summary line alone answers;
 * the hundred-row log and the three full template editors are what you go
 * looking for afterwards, so they stay behind a disclosure until asked for.
 */
$pageTitle = 'Emails';
$currentPage = 'emails';
require_once __DIR__ . '/partials/layout_header.php';

// Set when a template save redirects back here, so that template reopens
// rather than making the admin hunt for the one they just edited.
$savedTemplateId = isset($_GET['saved']) ? (int) $_GET['saved'] : 0;
?>
<div class="emails-container">

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">Template saved.</div>
  <?php endif; ?>

  <section class="email-section">
    <h2>Email queue</h2>
    <ul class="email-stats">
      <li><span class="email-stat-value"><?= (int) ($stats['pending'] ?? 0) ?></span> pending</li>
      <li><span class="email-stat-value"><?= (int) ($stats['sent'] ?? 0) ?></span> sent</li>
      <li><span class="email-stat-value"><?= (int) ($stats['failed'] ?? 0) ?></span> failed</li>
    </ul>

    <details class="email-disclosure">
      <summary>Recent emails<?= $queue ? ' (' . count($queue) . ')' : '' ?></summary>
      <?php if (empty($queue)): ?>
        <p class="no-data">No emails have been sent yet.</p>
      <?php else: ?>
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
              <?php
              // Queue status maps onto the shared status-badge palette rather
              // than a badge-pending/badge-sent set that was never styled.
              $statusClass = match ($email['status']) {
                  'sent' => 'status-ok',
                  'failed' => 'status-error',
                  default => 'status-warning',
              };
              ?>
              <tr>
                <td><?= e($email['recipient_email']) ?></td>
                <td><?= e(mb_strimwidth((string) $email['subject'], 0, 60, '...')) ?></td>
                <td><span class="status-badge <?= $statusClass ?>"><?= e(ucfirst($email['status'])) ?></span></td>
                <td><?= (int) $email['retry_count'] ?></td>
                <td><?= $email['sent_at'] ? e(date('M d H:i', strtotime($email['sent_at']))) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </details>
  </section>

  <section class="email-section">
    <h2>Templates</h2>
    <?php if (empty($templates)): ?>
      <p class="no-data">No email templates are installed.</p>
    <?php else: ?>
      <?php foreach ($templates as $template): ?>
        <?php $variables = json_decode((string) ($template['variables'] ?? '[]'), true) ?: []; ?>
        <details class="template-card" <?= $savedTemplateId === (int) $template['id'] ? 'open' : '' ?>>
          <summary class="template-summary">
            <span class="template-name"><?= e($template['display_name']) ?></span>
            <span class="status-badge <?= $template['enabled'] ? 'status-ok' : '' ?>">
              <?= $template['enabled'] ? 'Enabled' : 'Disabled' ?>
            </span>
          </summary>

          <form method="post" class="template-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_template">
            <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">

            <label for="subject-<?= (int) $template['id'] ?>">Subject</label>
            <input type="text" id="subject-<?= (int) $template['id'] ?>" name="subject_template"
                   value="<?= e($template['subject_template']) ?>">

            <label for="body-<?= (int) $template['id'] ?>">HTML body</label>
            <textarea id="body-<?= (int) $template['id'] ?>" name="body_html_template"
                      class="template-body"><?= e($template['body_html_template']) ?></textarea>

            <?php if ($variables): ?>
              <p class="template-variables">
                Available placeholders:
                <?php foreach ($variables as $variable): ?><code>{{<?= e($variable) ?>}}</code><?php endforeach; ?>
              </p>
            <?php endif; ?>

            <label class="template-enabled">
              <input type="checkbox" name="enabled" <?= $template['enabled'] ? 'checked' : '' ?>>
              Send this email
            </label>

            <button type="submit">Save template</button>
          </form>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
