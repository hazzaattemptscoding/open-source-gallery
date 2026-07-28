/**
 * Event page: lightbox, single-tap add-to-cart, and progressive-enhancement
 * filter bar. Dynamic values (photo IDs/tokens, current filter scope) come
 * from data-* attributes on #photoGrid and #filterForm rather than being
 * templated into this file, since app/views/*.php render this file's own
 * <script src> tag but never touch its contents — it's a plain static asset.
 */

const photoGrid = document.getElementById('photoGrid');
const photoIds = JSON.parse(photoGrid.dataset.photoIds || '[]');
const photoTokens = JSON.parse(photoGrid.dataset.photoTokens || '[]');
let lightboxIndex = -1;

function openLightbox(index) {
  lightboxIndex = index;
  updateLightbox();
  document.getElementById('lightbox').hidden = false;
  document.body.classList.add('lightbox-open');
  fetch('/api/photos/view', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ photo_id: photoIds[lightboxIndex] }),
  }).catch(() => {});
}

function closeLightbox() {
  document.getElementById('lightbox').hidden = true;
  document.body.classList.remove('lightbox-open');
}

function updateLightbox() {
  const token = photoTokens[lightboxIndex];
  document.getElementById('lightboxImage').src = `/media/d/${token}-1600.jpg`;
  [lightboxIndex - 1, lightboxIndex + 1].forEach(i => {
    if (i >= 0 && i < photoTokens.length) {
      new Image().src = `/media/d/${photoTokens[i]}-1600.jpg`;
    }
  });
}

function nextPhoto() {
  if (lightboxIndex < photoTokens.length - 1) {
    lightboxIndex++;
    updateLightbox();
  }
}

function prevPhoto() {
  if (lightboxIndex > 0) {
    lightboxIndex--;
    updateLightbox();
  }
}

photoGrid.addEventListener('click', (e) => {
  const cartBtn = e.target.closest('.add-to-cart');
  if (cartBtn) {
    e.stopPropagation();
    addToCart('photo', parseInt(cartBtn.dataset.photoId, 10), cartBtn);
    return;
  }
  const thumb = e.target.closest('.photo-thumb');
  if (thumb) {
    openLightbox(parseInt(thumb.dataset.index, 10));
  }
});

document.getElementById('lightboxClose').addEventListener('click', closeLightbox);
document.getElementById('lightboxNext').addEventListener('click', nextPhoto);
document.getElementById('lightboxPrev').addEventListener('click', prevPhoto);
document.getElementById('lightbox').addEventListener('click', (e) => {
  if (e.target.id === 'lightbox') closeLightbox();
});

document.addEventListener('keydown', (e) => {
  if (document.getElementById('lightbox').hidden) return;
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
    });
    if (!response.ok) throw new Error('Failed to add');
    const data = await response.json();
    document.getElementById('cartCount').textContent = data.count;
    if (btn) {
      btn.classList.add('added');
      btn.textContent = '✓';
    }
  } catch (err) {
    console.error(err);
  }
}

document.getElementById('lightboxCart').addEventListener('click', () => {
  addToCart('photo', photoIds[lightboxIndex], null);
});

// Progressive enhancement: filter changes re-fetch /api/photos instead of a full reload.
const filterForm = document.getElementById('filterForm');
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

  try {
    const response = await fetch(apiUrl);
    if (!response.ok) throw new Error('Filter failed');
    const html = await response.text();
    photoGrid.innerHTML = html;
    history.pushState({}, '', url);
  } catch (err) {
    window.location.href = url;
  }
});
