/**
 * Wishlist system: heart icon per photo, localStorage persistence (90-day TTL).
 * Works entirely client-side for anonymous users; no account required.
 */

const WISHLIST_KEY = 'gallery_wishlist';
const WISHLIST_TTL_DAYS = 90;

class Wishlist {
  constructor() {
    this.items = this.load();
  }

  load() {
    try {
      const stored = localStorage.getItem(WISHLIST_KEY);
      if (!stored) return {};

      const data = JSON.parse(stored);
      const now = Date.now();

      // Clean expired items (older than TTL)
      const cleaned = {};
      Object.entries(data).forEach(([photoId, timestamp]) => {
        const age = now - timestamp;
        const maxAge = WISHLIST_TTL_DAYS * 24 * 60 * 60 * 1000;
        if (age < maxAge) {
          cleaned[photoId] = timestamp;
        }
      });

      if (Object.keys(cleaned).length !== Object.keys(data).length) {
        this.save(cleaned);
      }
      return cleaned;
    } catch (e) {
      console.error('Failed to load wishlist:', e);
      return {};
    }
  }

  save(items = this.items) {
    try {
      localStorage.setItem(WISHLIST_KEY, JSON.stringify(items));
    } catch (e) {
      console.error('Failed to save wishlist:', e);
    }
  }

  add(photoId) {
    this.items[photoId] = Date.now();
    this.save();
    this.dispatchEvent('wishlist:updated');
  }

  remove(photoId) {
    delete this.items[photoId];
    this.save();
    this.dispatchEvent('wishlist:updated');
  }

  toggle(photoId) {
    if (this.has(photoId)) {
      this.remove(photoId);
      return false;
    } else {
      this.add(photoId);
      return true;
    }
  }

  has(photoId) {
    return photoId in this.items;
  }

  count() {
    return Object.keys(this.items).length;
  }

  getIds() {
    return Object.keys(this.items);
  }

  addAll(photoIds) {
    photoIds.forEach(id => {
      if (!this.has(id)) this.add(id);
    });
  }

  clear() {
    this.items = {};
    this.save();
    this.dispatchEvent('wishlist:updated');
  }

  dispatchEvent(eventName) {
    window.dispatchEvent(new CustomEvent(eventName, { detail: this }));
  }
}

// Global wishlist instance
const wishlist = new Wishlist();

// Initialize heart buttons on page load
document.addEventListener('DOMContentLoaded', () => {
  initializeHeartButtons();
  updateWishlistUI();
  window.addEventListener('wishlist:updated', updateWishlistUI);
});

function initializeHeartButtons() {
  const hearts = document.querySelectorAll('[data-photo-id].heart-button');
  hearts.forEach(heart => {
    const photoId = heart.dataset.photoId;
    if (wishlist.has(photoId)) {
      heart.classList.add('active');
    }
    heart.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const isActive = wishlist.toggle(photoId);
      heart.classList.toggle('active', isActive);
      showToast(isActive ? 'Added to wishlist' : 'Removed from wishlist');
    });
  });
}

function updateWishlistUI() {
  const count = wishlist.count();
  const wishlistBadge = document.querySelector('[data-wishlist-count]');
  if (wishlistBadge) {
    wishlistBadge.textContent = count;
    wishlistBadge.style.display = count > 0 ? 'inline' : 'none';
  }
}

function showToast(message) {
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  // Trigger animation
  requestAnimationFrame(() => {
    toast.classList.add('show');
  });

  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 2000);
}

// Heart button HTML snippet for reuse
function renderHeartButton(photoId, isActive = false) {
  const activeClass = isActive ? 'active' : '';
  return `<button class="heart-button ${activeClass}" data-photo-id="${photoId}" title="Add to wishlist" aria-label="Add to wishlist">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
    </svg>
  </button>`;
}
