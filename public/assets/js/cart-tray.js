/**
 * Persistent selection tray.
 *
 * Keeps a bottom bar in sync with the cart across the whole site: count and
 * total, plus a panel listing what is selected so nobody has to scroll back up
 * hunting for it.
 *
 * Design notes worth knowing before changing anything here:
 *
 *  - The count is authoritative from the server on page load (rendered into the
 *    markup) and updated optimistically from /cart/add and /cart/remove
 *    responses, which already return it for free.
 *
 *  - The total is NOT fetched per add. cart_price() costs one query per item on
 *    the server, so a 60-photo "buy all my photos" would otherwise cost around
 *    1,800 queries. Refreshes are debounced, so a bulk add resolves to a single
 *    pricing pass once the dust settles.
 *
 *  - Nothing here owns cart state. It listens and reflects. The signed cookie
 *    on the server remains the only source of truth, which is why the tray is
 *    still correct after a back-navigation or a hard reload.
 */
(function () {
  'use strict';

  var tray = document.getElementById('cartTray');
  if (!tray) return;

  var elCount = document.getElementById('cartTrayCount');
  var elLabel = document.getElementById('cartTrayCountLabel');
  var elTotal = document.getElementById('cartTrayTotal');
  var elPanel = document.getElementById('cartTrayPanel');
  var elLines = document.getElementById('cartTrayLines');
  var elToggle = document.getElementById('cartTrayToggle');
  var elClose = document.getElementById('cartTrayClose');
  var elHeaderCount = document.getElementById('cartCount');

  var refreshTimer = null;
  var panelOpen = false;
  var lastKnownCount = parseInt(elCount ? elCount.textContent : '0', 10) || 0;

  /** Show or hide the whole tray based on whether anything is selected. */
  function setVisible(visible) {
    if (visible) {
      tray.hidden = false;
      // Next frame, so the transition has a non-hidden element to animate from.
      window.requestAnimationFrame(function () { tray.classList.add('is-visible'); });
    } else {
      tray.classList.remove('is-visible');
      closePanel();
      tray.hidden = true;
    }
  }

  /** Apply a known count everywhere it is displayed. */
  function setCount(count) {
    if (typeof count !== 'number' || isNaN(count)) return;
    lastKnownCount = count;

    if (elCount) elCount.textContent = count;
    if (elLabel) elLabel.textContent = count === 1 ? 'photo' : 'photos';
    if (elHeaderCount) elHeaderCount.textContent = count;

    setVisible(count > 0);
  }

  /**
   * Pull count, total and lines from the server.
   *
   * Debounced: repeated calls inside the window collapse into one. This is what
   * keeps a bulk add from stampeding the pricing query.
   */
  function scheduleRefresh(delay) {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(refreshNow, typeof delay === 'number' ? delay : 400);
  }

  function refreshNow() {
    fetch('/cart/summary', { credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data || !data.ok) return;

        setCount(data.count);
        if (elTotal) elTotal.textContent = data.total_formatted || '';

        if (panelOpen) renderLines(data.lines);
      })
      .catch(function () {
        // The tray is a convenience. If the summary cannot be fetched the count
        // rendered by the server is still on screen and Checkout still works,
        // so failing quietly beats throwing an error at someone mid-browse.
      });
  }

  /** Draw the selected-items list inside the panel. */
  function renderLines(lines) {
    if (!elLines) return;
    elLines.textContent = '';

    if (!lines || !lines.length) {
      var empty = document.createElement('li');
      empty.className = 'cart-tray-empty';
      empty.textContent = 'Nothing selected yet.';
      elLines.appendChild(empty);
      return;
    }

    lines.forEach(function (line) {
      var li = document.createElement('li');
      li.className = 'cart-tray-line';

      if (line.public_token) {
        var img = document.createElement('img');
        img.className = 'cart-tray-thumb';
        img.src = '/media/d/' + line.public_token + '-400.jpg';
        img.alt = '';
        img.loading = 'lazy';
        li.appendChild(img);
      }

      var desc = document.createElement('span');
      desc.className = 'cart-tray-desc';
      // textContent, not innerHTML: descriptions contain event titles, which
      // are admin-supplied text and must never be parsed as markup here.
      desc.textContent = line.description;
      li.appendChild(desc);

      var price = document.createElement('span');
      price.className = 'cart-tray-price';
      price.textContent = line.price_formatted;
      li.appendChild(price);

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'cart-tray-remove';
      remove.setAttribute('aria-label', 'Remove from selection');
      remove.dataset.type = line.type;
      remove.dataset.id = line.id;
      remove.textContent = '×';
      li.appendChild(remove);

      elLines.appendChild(li);
    });
  }

  function openPanel() {
    if (!elPanel) return;
    panelOpen = true;
    elPanel.hidden = false;
    tray.classList.add('is-open');
    if (elToggle) elToggle.setAttribute('aria-expanded', 'true');
    refreshNow();
  }

  function closePanel() {
    if (!elPanel) return;
    panelOpen = false;
    elPanel.hidden = true;
    tray.classList.remove('is-open');
    if (elToggle) elToggle.setAttribute('aria-expanded', 'false');
  }

  function togglePanel() {
    if (panelOpen) closePanel(); else openPanel();
  }

  /** Remove one line straight from the panel. */
  function removeLine(button) {
    var type = button.dataset.type;
    var id = parseInt(button.dataset.id, 10);
    if (!type || !id) return;

    button.disabled = true;

    fetch('/cart/remove', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ type: type, id: id })
    })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (data && typeof data.count !== 'undefined') setCount(data.count);
        refreshNow();
      })
      .catch(function () { button.disabled = false; });
  }

  if (elToggle) elToggle.addEventListener('click', togglePanel);
  if (elClose) elClose.addEventListener('click', closePanel);

  if (elLines) {
    elLines.addEventListener('click', function (event) {
      var button = event.target.closest('.cart-tray-remove');
      if (button) removeLine(button);
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && panelOpen) closePanel();
  });

  /*
   * Watch every cart mutation on the page without having to modify each of the
   * scripts that performs one.
   *
   * event.js, favorites.js and entrant.js all POST to /cart/add or /cart/remove
   * independently. Wrapping fetch means the tray stays in sync with all of
   * them, and with anything added later, instead of each caller having to
   * remember to notify it. The original fetch is always called and its promise
   * returned untouched, so this cannot change how those callers behave.
   */
  var nativeFetch = window.fetch;
  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    var promise = nativeFetch.apply(this, arguments);

    if (url.indexOf('/cart/add') !== -1 || url.indexOf('/cart/remove') !== -1) {
      promise
        .then(function (response) {
          // Read a clone so the original body stays unconsumed for the caller.
          return response.clone().json().catch(function () { return null; });
        })
        .then(function (data) {
          if (data && typeof data.count !== 'undefined') setCount(data.count);
          scheduleRefresh();
        })
        .catch(function () { /* the caller handles its own errors */ });
    }

    return promise;
  };

  // Fill in the total for a cart that already had items when the page loaded.
  if (lastKnownCount > 0) scheduleRefresh(0);
})();
