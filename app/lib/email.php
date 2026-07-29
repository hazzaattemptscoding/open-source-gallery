<?php
/**
 * Email system: queue-based, template-driven notifications.
 * All emails are queued and sent by cron worker for reliability.
 */

declare(strict_types=1);

/**
 * Queue an email for sending.
 */
function queue_email(
    PDO $pdo,
    string $recipient,
    string $subject,
    string $bodyHtml,
    string $bodyText = '',
    string $template = '',
    array $data = [],
    string $purpose = 'notification'
): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO emails (recipient_email, subject, body_html, body_text, template_name, template_data, purpose, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        SQL);
        return $stmt->execute([
            $recipient,
            $subject,
            $bodyHtml,
            $bodyText ?: $bodyHtml,
            $template,
            !empty($data) ? json_encode($data) : null,
            $purpose,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Queue email using a template with variable substitution.
 */
function queue_email_from_template(
    PDO $pdo,
    string $recipient,
    string $templateName,
    array $variables,
    string $purpose = 'notification'
): bool {
    try {
        $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE name = ? AND enabled = 1');
        $stmt->execute([$templateName]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return false;
        }

        $subject = interpolate_template($template['subject_template'], $variables);
        $bodyHtml = interpolate_template($template['body_html_template'], $variables);
        $bodyText = interpolate_template($template['body_text_template'] ?? '', $variables);

        return queue_email($pdo, $recipient, $subject, $bodyHtml, $bodyText, $templateName, $variables, $purpose);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Interpolate template variables: {{var_name}} -> value
 */
function interpolate_template(string $template, array $variables): string {
    foreach ($variables as $key => $value) {
        $template = str_replace("{{$key}}", (string)$value, $template);
    }
    return $template;
}

/**
 * Get all pending emails.
 */
function get_pending_emails(PDO $pdo, int $limit = 50): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT * FROM emails
            WHERE status = 'pending' AND retry_count < 3
            ORDER BY created_at ASC
            LIMIT ?
        SQL);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Send email via configured mailer (PHP mail, SMTP, etc).
 */
function send_email_direct(string $recipient, string $subject, string $bodyHtml, string $bodyText = ''): bool {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@" . parse_url($_SERVER['HTTP_HOST'] ?? '', PHP_URL_HOST) . "\r\n";

    $body = $bodyText ?: strip_tags($bodyHtml);
    return (bool)mail($recipient, $subject, $body, $headers);
}

/**
 * Mark email as sent.
 */
function mark_email_sent(PDO $pdo, int $emailId): bool {
    try {
        $stmt = $pdo->prepare('UPDATE emails SET status = ?, sent_at = CURRENT_TIMESTAMP WHERE id = ?');
        return $stmt->execute(['sent', $emailId]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark email as failed and increment retry count.
 */
function mark_email_failed(PDO $pdo, int $emailId, string $error = ''): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            UPDATE emails
            SET status = ?, retry_count = retry_count + 1, error_message = ?
            WHERE id = ?
        SQL);
        return $stmt->execute(['failed', $error, $emailId]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get email statistics.
 */
function get_email_stats(PDO $pdo): ?array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM emails
        SQL);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get email queue (for admin dashboard).
 */
function get_email_queue(PDO $pdo, int $limit = 50): array {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT id, recipient_email, subject, status, retry_count, created_at, sent_at
            FROM emails
            ORDER BY created_at DESC
            LIMIT ?
        SQL);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get all email templates.
 */
function get_email_templates(PDO $pdo): array {
    try {
        $stmt = $pdo->query('SELECT * FROM email_templates ORDER BY display_name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Update email template.
 */
function update_email_template(PDO $pdo, int $templateId, array $data): bool {
    try {
        $stmt = $pdo->prepare(<<<'SQL'
            UPDATE email_templates
            SET subject_template = ?, body_html_template = ?, body_text_template = ?, enabled = ?
            WHERE id = ?
        SQL);
        return $stmt->execute([
            $data['subject_template'] ?? '',
            $data['body_html_template'] ?? '',
            $data['body_text_template'] ?? '',
            $data['enabled'] ?? 1,
            $templateId,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Cron worker: process email queue.
 */
function process_email_queue(PDO $pdo): int {
    $sent = 0;
    $emails = get_pending_emails($pdo, 100);

    foreach ($emails as $email) {
        if (send_email_direct($email['recipient_email'], $email['subject'], $email['body_html'], $email['body_text'])) {
            mark_email_sent($pdo, $email['id']);
            $sent++;
        } else {
            mark_email_failed($pdo, $email['id'], 'Failed to send via mail()');
        }
    }

    return $sent;
}
