/**
 * Enhanced lightbox: keyboard navigation (arrows, Esc), heart/share hotkeys,
 * metadata display, smooth transitions between photos.
 */

class EnhancedLightbox {
  constructor(photos = []) {
    this.photos = photos; // Array of {id, token, title}
    this.currentIndex = 0;
    this.isOpen = false;
    this.bindKeyboardEvents();
  }

  open(photoIndex = 0) {
    this.currentIndex = photoIndex;
    this.isOpen = true;
    this.render();
    document.body.style.overflow = 'hidden';
  }

  close() {
    this.isOpen = false;
    const lightbox = document.getElementById('enhanced-lightbox');
    if (lightbox) lightbox.remove();
    document.body.style.overflow = '';
  }

  next() {
    if (this.currentIndex < this.photos.length - 1) {
      this.currentIndex++;
      this.updateImage();
    }
  }

  prev() {
    if (this.currentIndex > 0) {
      this.currentIndex--;
      this.updateImage();
    }
  }

  render() {
    const photo = this.photos[this.currentIndex];
    const html = `
      <div id="enhanced-lightbox" class="lightbox">
        <button class="lightbox-close" aria-label="Close lightbox">&times;</button>
        <div class="lightbox-content">
          <img src="/media/d/${photo.token}-1600.jpg"
               alt="${photo.title}"
               class="lightbox-image"
               loading="lazy">
          <div class="lightbox-metadata">
            <strong>${photo.title}</strong>
            <p>${this.currentIndex + 1} / ${this.photos.length}</p>
          </div>
          <div class="lightbox-controls">
            <button class="prev" aria-label="Previous photo">← Prev</button>
            <button class="share" aria-label="Share">Share</button>
            <button class="heart" aria-label="Add to wishlist">♥</button>
            <button class="next" aria-label="Next photo">Next →</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);
    this.attachEventListeners();
  }

  updateImage() {
    const photo = this.photos[this.currentIndex];
    const lightbox = document.getElementById('enhanced-lightbox');
    if (!lightbox) return;

    const img = lightbox.querySelector('.lightbox-image');
    const metadata = lightbox.querySelector('.lightbox-metadata');

    img.style.opacity = '0';
    setTimeout(() => {
      img.src = `/media/d/${photo.token}-1600.jpg`;
      img.alt = photo.title;
      metadata.innerHTML = `
        <strong>${photo.title}</strong>
        <p>${this.currentIndex + 1} / ${this.photos.length}</p>
      `;
      img.style.opacity = '1';
    }, 100);
  }

  attachEventListeners() {
    const lightbox = document.getElementById('enhanced-lightbox');
    if (!lightbox) return;

    lightbox.querySelector('.lightbox-close').addEventListener('click', () => this.close());
    lightbox.querySelector('.prev').addEventListener('click', () => this.prev());
    lightbox.querySelector('.next').addEventListener('click', () => this.next());
    lightbox.querySelector('.share').addEventListener('click', () => this.share());
    lightbox.querySelector('.heart').addEventListener('click', () => this.toggleHeart());

    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) this.close();
    });
  }

  share() {
    const photo = this.photos[this.currentIndex];
    // Trigger sharing via the Sharing module if available
    if (window.PhotoSharer) {
      const eventSlug = new URLSearchParams(window.location.search).get('event') || 'gallery';
      const sharer = new PhotoSharer(photo.id, eventSlug, photo.title);
      sharer.openModal();
    }
  }

  toggleHeart() {
    if (window.wishlist) {
      const photo = this.photos[this.currentIndex];
      const isActive = wishlist.toggle(photo.id);
      const btn = document.querySelector('.lightbox-controls .heart');
      if (btn) {
        btn.classList.toggle('active', isActive);
        btn.textContent = isActive ? '♥' : '♡';
      }
    }
  }

  bindKeyboardEvents() {
    document.addEventListener('keydown', (e) => {
      if (!this.isOpen) return;

      switch (e.key) {
        case 'Escape':
          this.close();
          break;
        case 'ArrowLeft':
          e.preventDefault();
          this.prev();
          break;
        case 'ArrowRight':
          e.preventDefault();
          this.next();
          break;
        case 'h':
        case 'H':
          e.preventDefault();
          this.toggleHeart();
          break;
        case 's':
        case 'S':
          e.preventDefault();
          this.share();
          break;
      }
    });
  }
}

// Initialize lightbox when DOM is ready
let lightbox = null;
document.addEventListener('DOMContentLoaded', () => {
  // Parse photo data from grid
  const photoGrid = document.getElementById('photoGrid');
  if (!photoGrid) return;

  const photos = [];
  document.querySelectorAll('[data-photo-token]').forEach((el) => {
    photos.push({
      id: el.dataset.photoId,
      token: el.dataset.photoToken,
      title: el.dataset.photoTitle || 'Photo',
    });
  });

  if (photos.length > 0) {
    lightbox = new EnhancedLightbox(photos);

    // Bind click-to-open on photo cards
    document.addEventListener('click', (e) => {
      const photoCard = e.target.closest('[data-photo-token]');
      if (!photoCard) return;
      const index = photos.findIndex(p => p.token === photoCard.dataset.photoToken);
      if (index !== -1) {
        lightbox.open(index);
      }
    });
  }
});
