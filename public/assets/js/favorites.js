/**
 * Favourites page: remove a favourite in place, add every favourited photo
 * to the cart in one action.
 *
 * No server re-fetch on removal the way cart.js needs one: the cart's total
 * depends on a server-priced volume discount that can't be safely
 * recomputed client-side, but a favourites list has no price total of its
 * own to keep in sync, so removing a line here is a plain DOM removal after
 * the server confirms it.
 */

document.querySelectorAll('.favorites-item-remove').forEach(btn => {
  btn.addEventListener('click', () => handleRemove(btn));
});

async function handleRemove(btn) {
  const item = btn.closest('.favorites-item');
  const photoId = parseInt(item.dataset.photoId, 10);
  btn.disabled = true;

  try {
    const response = await fetch('/favorites/remove', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ photo_id: photoId }),
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('Failed to remove');
    item.remove();

    const grid = document.getElementById('favoritesGrid');
    if (grid && !grid.querySelector('.favorites-item')) {
      window.location.reload(); // show the empty state, same as cart.js's approach
    }
  } catch (err) {
    console.error(err);
    btn.disabled = false;
    showToast('Could not remove item. Please try again.');
  }
}

const addAllBtn = document.getElementById('addAllToCartBtn');
if (addAllBtn) {
  addAllBtn.addEventListener('click', async () => {
    const photoIds = Array.from(document.querySelectorAll('.favorites-item'))
      .map(el => parseInt(el.dataset.photoId, 10));

    addAllBtn.disabled = true;
    addAllBtn.textContent = 'Adding…';

    // Sequential, not Promise.all: cart_add() reads-then-writes the cart
    // cookie per call (app/lib/cart.php), so concurrent requests racing on
    // the same cookie could drop items -- the last write wins on a plain
    // cookie, there's no server-side lock the way a DB row would have.
    let added = 0;
    for (const photoId of photoIds) {
      try {
        const response = await fetch('/cart/add', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'photo', id: photoId }),
          credentials: 'same-origin',
        });
        if (response.ok) added++;
      } catch (err) {
        console.error(err);
      }
    }

    if (added > 0) {
      window.location.href = '/cart';
    } else {
      addAllBtn.disabled = false;
      addAllBtn.textContent = 'Add all to cart';
      showToast('Could not add items to cart. Please try again.');
    }
  });
}

function showToast(message) {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  window.clearTimeout(toast._hideTimer);
  requestAnimationFrame(() => toast.classList.add('is-visible'));
  toast._hideTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2600);
}
