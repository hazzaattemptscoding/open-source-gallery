<?php
declare(strict_types=1);

require_once __DIR__ . '/currency.php';

/**
 * Email templates for transactional messages.
 * All templates support variable interpolation via {var_name}.
 */

/**
 * Order confirmation email template.
 * Variables: customer_email, order_token, order_date, items, total, download_link, download_expires, site_name, gallery_url
 */
function email_template_order_confirmation(array $vars): string {
    $siteName = e($vars['site_name'] ?? 'Gallery');
    $customerEmail = e($vars['customer_email'] ?? '');
    $orderToken = e($vars['order_token'] ?? '');
    $orderDate = e($vars['order_date'] ?? '');
    $total = e($vars['total'] ?? '');
    $downloadLink = e($vars['download_link'] ?? '');
    $downloadExpires = e($vars['download_expires'] ?? '');
    $galleryUrl = e($vars['gallery_url'] ?? '');
    $itemsHtml = '';

    if (!empty($vars['items']) && is_array($vars['items'])) {
        foreach ($vars['items'] as $item) {
            $itemsHtml .= '<tr>';
            $itemsHtml .= '<td style="padding: 12px; border-bottom: 1px solid #eee; text-align: left;">';
            $itemsHtml .= e($item['description'] ?? 'Photo');
            $itemsHtml .= '</td>';
            $itemsHtml .= '<td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">' . (int)($item['quantity'] ?? 1) . '</td>';
            $itemsHtml .= '<td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">' . e($item['price'] ?? '—') . '</td>';
            $itemsHtml .= '</tr>';
        }
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111; line-height: 1.6; margin: 0; padding: 0; background: #f7f6f3;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <!-- Header -->
        <div style="border-bottom: 1px solid #eee; padding-bottom: 24px; margin-bottom: 24px;">
            <h1 style="margin: 0 0 8px; font-size: 28px; font-family: 'Newsreader', Georgia, serif; font-weight: 700; letter-spacing: -0.02em;">Order Confirmation</h1>
            <p style="margin: 0; color: #999; font-size: 14px;">Order #{$orderToken}</p>
        </div>

        <!-- Main content -->
        <div style="margin-bottom: 32px;">
            <p style="margin: 0 0 16px;">Thank you for your purchase, <strong>{$customerEmail}</strong>!</p>
            <p style="margin: 0 0 24px; color: #666;">Your order from <strong>{$orderDate}</strong> is complete. Download your files below or revisit the gallery anytime to view and purchase more photos.</p>

            <!-- Order summary table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <thead>
                    <tr style="background: #f7f6f3;">
                        <th style="padding: 12px; border-bottom: 2px solid #eee; text-align: left; font-weight: 600;">Item</th>
                        <th style="padding: 12px; border-bottom: 2px solid #eee; text-align: center; font-weight: 600;">Qty</th>
                        <th style="padding: 12px; border-bottom: 2px solid #eee; text-align: right; font-weight: 600;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsHtml}
                </tbody>
                <tfoot>
                    <tr style="background: #fafaf8;">
                        <td colspan="2" style="padding: 12px; text-align: right; font-weight: 600;">Total:</td>
                        <td style="padding: 12px; text-align: right; font-weight: 700; font-size: 18px;">{$total}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Download CTA -->
            <div style="background: #f7f6f3; padding: 24px; border-radius: 8px; margin-bottom: 24px; text-align: center;">
                <p style="margin: 0 0 16px; color: #666; font-size: 14px;">Your files are ready to download.</p>
                <a href="{$downloadLink}" style="display: inline-block; padding: 12px 32px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 160ms ease-out;">Download Files</a>
                <p style="margin: 16px 0 0; color: #999; font-size: 12px;">Link expires in {$downloadExpires}.</p>
            </div>

            <!-- Gallery link -->
            <div style="margin-bottom: 24px; text-align: center;">
                <p style="margin: 0; color: #666;">Ready for more? <a href="{$galleryUrl}" style="color: #111; font-weight: 600; text-decoration: none;">View the gallery →</a></p>
            </div>
        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #eee; padding-top: 24px; color: #999; font-size: 12px;">
            <p style="margin: 0 0 8px;"><strong>{$siteName}</strong></p>
            <p style="margin: 0;">This is an automated message. Do not reply to this email.</p>
            <p style="margin: 8px 0 0;">Questions? Contact us directly.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Abandoned cart email template (sent 24 hours after cart created but not checked out).
 */
function email_template_abandoned_cart(array $vars): string {
    $siteName = e($vars['site_name'] ?? 'Gallery');
    $cartUrl = e($vars['cart_url'] ?? '');
    $itemCount = (int)($vars['item_count'] ?? 0);
    $expiresIn = e($vars['expires_in'] ?? '7 days');
    $itemNoun = $itemCount === 1 ? 'photo' : 'photos';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Your Purchase</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111; line-height: 1.6; margin: 0; padding: 0; background: #f7f6f3;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <h1 style="margin: 0 0 16px; font-size: 28px; font-family: 'Newsreader', Georgia, serif; font-weight: 700; letter-spacing: -0.02em;">You left {$itemCount} {$itemNoun} behind</h1>
        <p style="margin: 0 0 24px; color: #666;">Your cart is waiting. Complete your purchase before your items expire.</p>

        <div style="background: #f7f6f3; padding: 24px; border-radius: 8px; margin-bottom: 24px; text-align: center;">
            <a href="{$cartUrl}" style="display: inline-block; padding: 12px 32px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 160ms ease-out;">Complete Purchase</a>
            <p style="margin: 16px 0 0; color: #999; font-size: 12px;">Your items will expire in {$expiresIn}.</p>
        </div>

        <div style="border-top: 1px solid #eee; padding-top: 24px; color: #999; font-size: 12px;">
            <p style="margin: 0;"><strong>{$siteName}</strong></p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Download ready notification (for order items that were in processing, now ready).
 */
function email_template_download_ready(array $vars): string {
    $siteName = e($vars['site_name'] ?? 'Gallery');
    $orderToken = e($vars['order_token'] ?? '');
    $downloadLink = e($vars['download_link'] ?? '');
    $itemsReady = (int)($vars['items_ready'] ?? 1);
    $itemsReadyVerb = $itemsReady === 1 ? 'file is' : 'files are';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Files Are Ready</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111; line-height: 1.6; margin: 0; padding: 0; background: #f7f6f3;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <h1 style="margin: 0 0 16px; font-size: 28px; font-family: 'Newsreader', Georgia, serif; font-weight: 700; letter-spacing: -0.02em;">Ready to Download</h1>
        <p style="margin: 0 0 24px; color: #666;">{$itemsReady} {$itemsReadyVerb} ready for download from your order #{$orderToken}.</p>

        <div style="background: #f7f6f3; padding: 24px; border-radius: 8px; margin-bottom: 24px; text-align: center;">
            <a href="{$downloadLink}" style="display: inline-block; padding: 12px 32px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 160ms ease-out;">Download Now</a>
        </div>

        <div style="border-top: 1px solid #eee; padding-top: 24px; color: #999; font-size: 12px;">
            <p style="margin: 0;"><strong>{$siteName}</strong></p>
        </div>
    </div>
</body>
</html>
HTML;
}
