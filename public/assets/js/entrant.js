/**
 * Personal page behaviour: the "That's me" / "Not me" controls, and the
 * buy-all-my-photos button.
 *
 * Everything here is progressive enhancement. Without JavaScript the personal
 * page still lists photos, still paginates through ?page=, and still lets you
 * add photos to the cart through the normal grid controls. Only the review
 * controls and the bulk add need this file.
 */
(function () {
  'use strict';

  var page = document.querySelector('.entrant-page');
  if (!page) return;

  var token = page.dataset.entrantToken;
  var csrfToken = page.dataset.csrfToken;

  /**
   * Send one verdict to the server and remove the tile on success.
   *
   * The tile is removed either way, "That's me" or "Not me": in both cases the
   * visitor has answered and should not be asked again, and leaving a rejected
   * photo sitting there looks like the click did nothing.
   */
  function sendVerdict(button) {
    var tile = button.closest('.entrant-maybe-tile');
    if (!tile) return;

    var photoId = parseInt(button.dataset.photoId, 10);
    var verdict = button.dataset.verdict;
    if (!photoId || !verdict) return;

    // Disable both buttons in this tile immediately. Without this a double tap
    // on a slow connection fires two requests, and the second gets a
    // "changed: false" because reviewed_at is already set.
    var buttons = tile.querySelectorAll('.entrant-verdict');
    buttons.forEach(function (b) { b.disabled = true; });

    fetch('/entrant/review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: csrfToken,
        token: token,
        photo_id: photoId,
        verdict: verdict
      })
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          buttons.forEach(function (b) { b.disabled = false; });
          showMessage(result.data && result.data.error ? result.data.error : 'Could not save that. Try again.');
          return;
        }

        tile.classList.add('is-resolved');
        // Let the fade play before removing, then tidy up the whole section
        // once the last tile has gone so an empty heading is not left behind.
        window.setTimeout(function () {
          tile.remove();
          var remaining = document.querySelectorAll('.entrant-maybe-tile');
          if (remaining.length === 0) {
            var section = document.getElementById('entrantMaybe');
            if (section) section.remove();
          }
        }, 200);

        if (verdict === 'mine') {
          showMessage('Added to your photos.', 'success');
        }
      })
      .catch(function () {
        buttons.forEach(function (b) { b.disabled = false; });
        showMessage('Could not save that. Check your connection and try again.');
      });
  }

  /**
   * Add every photo on this page to the cart.
   *
   * Posts to the same /cart/add endpoint the grid uses, one photo at a time,
   * rather than inventing a bulk endpoint. Sequential rather than parallel so a
   * page of 60 does not open 60 connections at once on a phone.
   */
  function buyAll(button) {
    var tiles = document.querySelectorAll('#entrantGrid .photo-tile');
    if (!tiles.length) return;

    var originalLabel = button.textContent;
    button.disabled = true;

    var ids = Array.prototype.map.call(tiles, function (tile) {
      return parseInt(tile.dataset.photoId, 10);
    }).filter(Boolean);

    var added = 0;
    var latestCount;

    function next(index) {
      if (index >= ids.length) {
        button.textContent = originalLabel;
        button.disabled = false;
        showMessage(
          added + (added === 1 ? ' photo added to your cart.' : ' photos added to your cart.'),
          'success'
        );
        setCartCount(latestCount);
        return;
      }

      button.textContent = 'Adding ' + (index + 1) + ' of ' + ids.length + '…';

      fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'photo', id: ids[index] })
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          // "already in cart" is a success from the visitor's point of view:
          // the photo they wanted is in the cart either way.
          if (data && (data.ok || data.error === 'Photo already in cart')) added++;
          if (data && typeof data.count !== 'undefined') latestCount = data.count;
          next(index + 1);
        })
        .catch(function () {
          next(index + 1);
        });
    }

    next(0);
  }

  /**
   * Update the header cart badge.
   *
   * The count comes from the /cart/add response rather than a separate lookup:
   * there is no /cart/count endpoint, and adding one just to refresh a badge
   * would be a round trip for something the previous response already told us.
   */
  function setCartCount(count) {
    if (typeof count === 'undefined') return;
    var el = document.getElementById('cartCount');
    if (el) el.textContent = count;

    var badge = document.getElementById('cartBadge');
    if (!badge) return;
    badge.classList.remove('is-bumped');
    void badge.offsetWidth; // restart the animation on rapid successive adds
    badge.classList.add('is-bumped');
  }

  /**
   * Site-wide toast, with a guard.
   *
   * UIFeedback is declared with const at the top level of ui-feedback.js, which
   * puts it in the global lexical scope but NOT on window, so it has to be
   * referenced bare and tested with typeof rather than as window.UIFeedback.
   */
  function showMessage(text, type) {
    if (typeof UIFeedback !== 'undefined' && typeof UIFeedback.showToast === 'function') {
      UIFeedback.showToast(text, type || 'info');
    }
  }

  page.addEventListener('click', function (event) {
    var verdictButton = event.target.closest('.entrant-verdict');
    if (verdictButton) {
      event.preventDefault();
      sendVerdict(verdictButton);
      return;
    }

    var buyAllButton = event.target.closest('[data-entrant-buy-all]');
    if (buyAllButton) {
      event.preventDefault();
      buyAll(buyAllButton);
    }
  });
})();
