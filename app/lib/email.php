<?php
/**
 * Read/write helpers backing the admin email management page
 * (app/controllers/admin/emails.php): queue stats, queue listing, and
 * template CRUD against the `emails` / `email_templates` tables.
 *
 * This file used to also hold a queue-and-send subsystem (queue_email(),
 * queue_email_from_template(), process_email_queue(), etc.) that nothing in
 * the app ever called — receipts and refunds go straight out through
 * app/lib/mailer.php's send_receipt_email()/send_refund_email() instead of
 * enqueuing a row here. That half was correctly identified as dead and
 * removed, but the removal took the whole file with it, including these four
 * functions the admin page still requires on every request. Restored to just
 * the live half.
 */

declare(strict_types=1);

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
        error_log('Failed to get email stats: ' . $e->getMessage());
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
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Failed to get email queue: ' . $e->getMessage());
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
        error_log('Failed to get email templates: ' . $e->getMessage());
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
        error_log('Failed to update email template ' . $templateId . ': ' . $e->getMessage());
        return false;
    }
}
