/**
 * Event page: lightbox, single-tap add-to-cart, and progressive-enhancement
 * filter bar. Dynamic values (photo IDs/tokens, current filter scope) come
 * from data-* attributes on #photoGrid and #filterForm rather than being
 * templated into this file, since app/views/*.php render this file's own
 * <script src> tag but never touch its contents — it's a plain static asset.
 */

const photoGrid = document.getElementById('photoGrid');
let lightboxIndex = -1;

// The source of truth for "what photos exist right now" is the DOM, not a
// JSON blob parsed once at load. Two things change the grid's contents after
// load -- a filter change replaces it entirely, "Load more" appends to it --
// and a snapshot taken once at module load would go stale the moment either
// happens. Re-querying is cheap at gallery sizes (tens to low hundreds of
// thumbs) and means the lightbox is automatically correct after both.
function currentThumbs() {
  return Array.from(photoGrid.querySelectorAll('.photo-thumb'));
}

function openLightbox(index) {
  lightboxIndex = index;
  updateLightbox(true);
  const lightbox = document.getElementById('lightbox');
  requestAnimationFrame(() => lightbox.classList.add('is-open'));
  document.body.classList.add('lightbox-open');
  const thumb = currentThumbs()[lightboxIndex];
  if (!thumb) return;
  fetch('/api/photos/view', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ photo_id: parseInt(thumb.dataset.photoId, 10) }),
  }).catch(() => {});
}

function closeLightbox() {
  document.getElementById('lightbox').classList.remove('is-open');
  document.body.classList.remove('lightbox-open');
}

// `immediate` skips the crossfade for the first image of a session, so
// opening the lightbox doesn't add extra latency before the first paint.
function updateLightbox(immediate) {
  const thumbs = currentThumbs();
  if (lightboxIndex < 0 || lightboxIndex >= thumbs.length) return;

  const img = document.getElementById('lightboxImage');
  const token = thumbs[lightboxIndex].dataset.token;

  if (immediate) {
    img.src = `/media/d/${token}-1600.jpg`;
  } else {
    img.classList.add('is-swapping');
    window.setTimeout(() => {
      img.src = `/media/d/${token}-1600.jpg`;
      img.classList.remove('is-swapping');
    }, 130);
  }

  [lightboxIndex - 1, lightboxIndex + 1].forEach(i => {
    if (i >= 0 && i < thumbs.length) {
      new Image().src = `/media/d/${thumbs[i].dataset.token}-1600.jpg`;
    }
  });
}

function nextPhoto() {
  if (lightboxIndex < currentThumbs().length - 1) {
    lightboxIndex++;
    updateLightbox(false);
  }
}

function prevPhoto() {
  if (lightboxIndex > 0) {
    lightboxIndex--;
    updateLightbox(false);
  }
}

photoGrid.addEventListener('click', (e) => {
  const cartBtn = e.target.closest('.add-to-cart');
  if (cartBtn) {
    e.stopPropagation();
    addToCart('photo', parseInt(cartBtn.dataset.photoId, 10), cartBtn);
    return;
  }
  // The empty-state's "Clear filters" button, when this grid was populated
  // by the /api/photos AJAX fragment rather than a full page load: the
  // fragment has no server-rendered $basePath to inline into an onclick,
  // so it reads the same data-base-path the filter form already carries
  // (see the progressive-enhancement filter handler below) instead.
  const resetBtn = e.target.closest('[data-reset-filters]');
  if (resetBtn) {
    const basePath = filterForm ? filterForm.dataset.basePath : '/';
    window.location.href = basePath || '/';
    return;
  }
  const thumb = e.target.closest('.photo-thumb');
  if (thumb) {
    openLightbox(currentThumbs().indexOf(thumb));
  }
});

// Handle clear filters button from filter form
document.addEventListener('click', (e) => {
  const clearBtn = e.target.closest('.filter-bar .clear-filters');
  if (clearBtn && clearBtn.dataset.clearHref) {
    window.location.href = clearBtn.dataset.clearHref;
  }
});

document.getElementById('lightboxClose').addEventListener('click', closeLightbox);
document.getElementById('lightboxNext').addEventListener('click', nextPhoto);
document.getElementById('lightboxPrev').addEventListener('click', prevPhoto);
document.getElementById('lightbox').addEventListener('click', (e) => {
  if (e.target.id === 'lightbox') closeLightbox();
});

document.addEventListener('keydown', (e) => {
  if (!document.getElementById('lightbox').classList.contains('is-open')) return;
  if (e.key === 'Escape') closeLightbox();
  if (e.key === 'ArrowRight') nextPhoto();
  if (e.key === 'ArrowLeft') prevPhoto();
});

let touchStartX = 0;
document.getElementById('lightbox').addEventListener('touchstart', (e) => {
  touchStartX = e.touches[0].clientX;
});
document.getElementById('lightbox').addEventListener('touchend', (e) => {
  const dx = e.changedTouches[0].clientX - touchStartX;
  if (dx > 50) prevPhoto();
  if (dx < -50) nextPhoto();
});

async function addToCart(type, id, btn) {
  try {
    const response = await fetch('/cart/add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id }),
      credentials: 'same-origin',
    });
    const data = await response.json();
    if (!response.ok) {
      if (data.error === 'Photo already in cart') {
        showToast('This photo is already in your cart.');
      } else {
        throw new Error(data.error || 'Failed to add');
      }
      return;
    }
    bumpCartCount(data.count);
    if (btn) {
      btn.classList.add('added');
      btn.textContent = '✓';
    }
  } catch (err) {
    console.error(err);
    showToast('Failed to add to cart. Please try again.');
  }
}

function bumpCartCount(count) {
  document.getElementById('cartCount').textContent = count;
  const cartBadge = document.getElementById('cartBadge');
  if (!cartBadge) return;
  cartBadge.classList.remove('is-bumped');
  void cartBadge.offsetWidth; // restart the animation on rapid successive adds
  cartBadge.classList.add('is-bumped');
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

document.getElementById('lightboxCart').addEventListener('click', () => {
  const thumbs = currentThumbs();
  if (lightboxIndex >= 0 && lightboxIndex < thumbs.length) {
    addToCart('photo', parseInt(thumbs[lightboxIndex].dataset.photoId, 10), null);
  }
});

// Progressive enhancement: filter changes re-fetch /api/photos instead of a full reload.
const filterForm = document.getElementById('filterForm');
if (filterForm) {
  filterForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const params = new URLSearchParams(new FormData(e.target));
    const basePath = filterForm.dataset.basePath;
    const eventSlug = filterForm.dataset.eventSlug;
    const sessionSlug = filterForm.dataset.sessionSlug;
    const url = `${basePath}?${params.toString()}`;

    const apiParams = new URLSearchParams(params);
    apiParams.set('event', eventSlug);
    if (sessionSlug) apiParams.set('session', sessionSlug);
    const apiUrl = `/api/photos?${apiParams.toString()}`;

    photoGrid.classList.add('is-loading');

    try {
      const response = await fetch(apiUrl);
      if (!response.ok) throw new Error('Filter failed');
      const html = await response.text();
      photoGrid.innerHTML = html;
      history.pushState({}, '', url);
      // A filter change is a new result set, so pagination restarts at 1
      // regardless of where the previous set's "Load more" had gotten to.
      currentPage = 1;
      syncLoadMoreState(response.headers.get('X-Has-More') === '1');
    } catch (err) {
      window.location.href = url;
    } finally {
      photoGrid.classList.remove('is-loading');
    }
  });
}

// "Load more": appends rather than replaces, so the lightbox (which reads
// the live DOM, see currentThumbs() above) picks up the new photos for free.
let currentPage = parseInt(photoGrid.dataset.page || '1', 10);
const loadMoreBtn = document.getElementById('loadMoreBtn');

function syncLoadMoreState(hasMore) {
  if (!loadMoreBtn) return;
  loadMoreBtn.hidden = !hasMore;
}

if (loadMoreBtn) {
  loadMoreBtn.addEventListener('click', async () => {
    const basePath = filterForm ? filterForm.dataset.basePath : loadMoreBtn.dataset.basePath;
    const eventSlug = filterForm ? filterForm.dataset.eventSlug : loadMoreBtn.dataset.eventSlug;
    const sessionSlug = filterForm ? filterForm.dataset.sessionSlug : loadMoreBtn.dataset.sessionSlug;

    // Carry whatever filters are currently applied, read from the live filter
    // form if present rather than a stale copy, so "Load more" after a filter
    // change fetches the next page of the FILTERED set, not the full gallery.
    const params = filterForm ? new URLSearchParams(new FormData(filterForm)) : new URLSearchParams();
    params.set('event', eventSlug);
    if (sessionSlug) params.set('session', sessionSlug);
    params.set('page', String(currentPage + 1));

    loadMoreBtn.disabled = true;
    loadMoreBtn.textContent = 'Loading…';

    try {
      const response = await fetch(`/api/photos?${params.toString()}`);
      if (!response.ok) throw new Error('Load more failed');
      const html = await response.text();

      const temp = document.createElement('div');
      temp.innerHTML = html;
      // The fragment can be the empty-state markup if a race lands here with
      // nothing left; only append real thumbs.
      temp.querySelectorAll('.photo-thumb').forEach(node => photoGrid.appendChild(node));

      currentPage++;
      syncLoadMoreState(response.headers.get('X-Has-More') === '1');
    } catch (err) {
      showToast('Could not load more photos. Please try again.');
    } finally {
      loadMoreBtn.disabled = false;
      loadMoreBtn.textContent = 'Load more';
    }
  });
}

// Search functionality: client-side filtering of photos by tag text
const searchInput = document.getElementById('searchInput');
if (searchInput) {
  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.toLowerCase().trim();
    const thumbs = photoGrid.querySelectorAll('.photo-thumb');
    let visibleCount = 0;

    thumbs.forEach(thumb => {
      // Kart and class only. Driver names are not present in the public
      // markup, so there is nothing to match against and nothing to leak.
      const kart = (thumb.dataset.kartTags || '').toLowerCase();
      const classList = (thumb.dataset.classTags || '').toLowerCase();
      const matches = query === '' ||
                      kart.includes(query) ||
                      classList.includes(query);
      thumb.style.display = matches ? '' : 'none';
      if (matches) visibleCount++;
    });

    // Show/hide empty state
    let emptyState = photoGrid.querySelector('.empty-state');
    if (visibleCount === 0 && !emptyState) {
      const div = document.createElement('div');
      div.className = 'empty-state';
      div.style.gridColumn = '1 / -1';
      const p = document.createElement('p');
      p.textContent = 'No photos match your search.';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'clear-filters';
      btn.textContent = 'Clear search';
      btn.addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('searchInput').dispatchEvent(new Event('input'));
      });
      div.appendChild(p);
      div.appendChild(btn);
      photoGrid.appendChild(div);
    } else if (visibleCount > 0 && emptyState) {
      emptyState.remove();
    }
  });
}
