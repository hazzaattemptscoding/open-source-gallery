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
    const lightbox = document.createElement('div');
    lightbox.id = 'enhanced-lightbox';
    lightbox.className = 'lightbox';

    const closeBtn = document.createElement('button');
    closeBtn.className = 'lightbox-close';
    closeBtn.setAttribute('aria-label', 'Close lightbox');
    closeBtn.textContent = '×';

    const content = document.createElement('div');
    content.className = 'lightbox-content';

    const img = document.createElement('img');
    img.src = `/media/d/${photo.token}-1600.jpg`;
    img.alt = photo.title;
    img.className = 'lightbox-image';
    img.loading = 'lazy';

    const metadata = document.createElement('div');
    metadata.className = 'lightbox-metadata';
    const title = document.createElement('strong');
    title.textContent = photo.title;
    const count = document.createElement('p');
    count.textContent = `${this.currentIndex + 1} / ${this.photos.length}`;
    metadata.appendChild(title);
    metadata.appendChild(count);

    const controls = document.createElement('div');
    controls.className = 'lightbox-controls';
    const prevBtn = document.createElement('button');
    prevBtn.className = 'prev';
    prevBtn.setAttribute('aria-label', 'Previous photo');
    prevBtn.textContent = '← Prev';
    const shareBtn = document.createElement('button');
    shareBtn.className = 'share';
    shareBtn.setAttribute('aria-label', 'Share');
    shareBtn.textContent = 'Share';
    const heartBtn = document.createElement('button');
    heartBtn.className = 'heart';
    heartBtn.setAttribute('aria-label', 'Add to wishlist');
    heartBtn.textContent = '◆';
    const nextBtn = document.createElement('button');
    nextBtn.className = 'next';
    nextBtn.setAttribute('aria-label', 'Next photo');
    nextBtn.textContent = 'Next →';
    controls.appendChild(prevBtn);
    controls.appendChild(shareBtn);
    controls.appendChild(heartBtn);
    controls.appendChild(nextBtn);

    content.appendChild(closeBtn);
    content.appendChild(img);
    content.appendChild(metadata);
    content.appendChild(controls);
    lightbox.appendChild(content);

    document.body.appendChild(lightbox);
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
      const titleEl = metadata.querySelector('strong');
      if (titleEl) titleEl.textContent = photo.title;
      const countEl = metadata.querySelector('p');
      if (countEl) countEl.textContent = `${this.currentIndex + 1} / ${this.photos.length}`;
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
        btn.textContent = isActive ? '●' : '○';
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
