<?php
/*
 * The selection tray rides along with the cart badge: any page that shows one
 * should show the other, so it hangs off the same $showCart flag rather than
 * each view having to remember to include it.
 */
if (!empty($showCart)) {
    require __DIR__ . '/cart-tray.php';
}
?>
<!-- Common scripts for all public pages -->
<script src="/assets/js/page-init.js" defer></script>
<script src="/assets/js/accessibility.js" defer></script>
<script src="/assets/js/ui-feedback.js" defer></script>
<?php if (!empty($showCart)): ?>
<script src="/assets/js/cart-tray.js" defer></script>
<?php endif; ?>
<?= isset($pageScripts) ? $pageScripts : '' ?>
</body>
</html>
