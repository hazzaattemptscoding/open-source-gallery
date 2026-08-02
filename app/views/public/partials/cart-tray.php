<?php
/**
 * The persistent selection tray.
 *
 * A bar pinned to the bottom of the viewport showing how many photos are
 * selected, what they come to, and a way straight to checkout. It appears on
 * any page that shows the cart badge, and only once something is in the cart.
 *
 * Why it exists: the cart survives navigation (it is a signed cookie) but there
 * was nothing on screen that said so once you scrolled past the header, and the
 * header badge was rendering a hardcoded zero on every page load. Selecting
 * photos therefore felt like it was not sticking. This gives the selection a
 * permanent, visible home.
 *
 * The count is rendered server-side so the tray is correct in the first frame,
 * with no flash of an empty state and no dependency on JavaScript to tell the
 * truth. The total arrives a moment later from /cart/summary, because pricing
 * costs a query per item and should not be on the critical path of every
 * gallery page load.
 *
 * Expects $showCart to be true and $config to be available.
 */

require_once __DIR__ . '/../../../lib/cart.php';

$trayItems = cart_get($GLOBALS['config']);
$trayCount = count($trayItems);
?>

<div
  class="cart-tray<?= $trayCount > 0 ? ' is-visible' : '' ?>"
  id="cartTray"
  data-cart-tray
  role="region"
  aria-label="Selected photos"
  <?= $trayCount > 0 ? '' : 'hidden' ?>>

  <div class="cart-tray-inner">
    <button type="button" class="cart-tray-summary" id="cartTrayToggle" aria-expanded="false" aria-controls="cartTrayPanel">
      <span class="cart-tray-count">
        <span id="cartTrayCount"><?= (int)$trayCount ?></span>
        <span class="cart-tray-count-label" id="cartTrayCountLabel"><?= $trayCount === 1 ? 'photo' : 'photos' ?></span>
      </span>
      <?php /* Until /cart/summary answers, this stays blank rather than showing
               a zero that would be wrong for a non-empty cart. */ ?>
      <span class="cart-tray-total" id="cartTrayTotal" aria-live="polite"></span>
      <span class="cart-tray-view">View</span>
    </button>

    <a href="/cart" class="cart-tray-checkout">Checkout</a>
  </div>

  <?php /* The "view selected" panel, so nobody has to scroll back up hunting
           for what they picked. Populated on first open. */ ?>
  <div class="cart-tray-panel" id="cartTrayPanel" hidden>
    <div class="cart-tray-panel-head">
      <h2 class="cart-tray-panel-title">Your selection</h2>
      <button type="button" class="cart-tray-close" id="cartTrayClose" aria-label="Close selection">&times;</button>
    </div>
    <ul class="cart-tray-lines" id="cartTrayLines">
      <li class="cart-tray-loading">Loading your selection…</li>
    </ul>
  </div>
</div>
