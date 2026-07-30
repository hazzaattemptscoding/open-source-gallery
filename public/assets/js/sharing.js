/**
 * Social sharing: copy link, email, Twitter, Facebook, Pinterest.
 * Generates shareable links with photo metadata in Open Graph preview.
 */

class PhotoSharer {
  constructor(photoId, eventSlug, photoTitle = 'Photo') {
    this.photoId = photoId;
    this.eventSlug = eventSlug;
    this.photoTitle = photoTitle;
    this.shareUrl = this.generateShareUrl();
  }

  generateShareUrl() {
    const baseUrl = window.location.origin;
    return `${baseUrl}/e/${this.eventSlug}?photo=${this.photoId}`;
  }

  getShareData() {
    return {
      url: this.shareUrl,
      title: this.photoTitle,
      text: `Check out this photo from ${this.photoTitle}`,
    };
  }

  copyToClipboard() {
    const text = this.shareUrl;
    if (navigator.clipboard) {
      return navigator.clipboard.writeText(text)
        .then(() => showToast('Link copied to clipboard'))
        .catch(err => console.error('Copy failed:', err));
    } else {
      // Fallback for older browsers
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      showToast('Link copied to clipboard');
    }
  }

  shareEmail() {
    const subject = encodeURIComponent(`Check out: ${this.photoTitle}`);
    const body = encodeURIComponent(`I wanted to share this photo with you:\n\n${this.shareUrl}`);
    window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
  }

  shareTwitter() {
    const text = encodeURIComponent(`${this.photoTitle}\n${this.shareUrl}`);
    window.open(`https://twitter.com/intent/tweet?text=${text}`, 'twitter-share', 'width=600,height=400');
  }

  shareFacebook() {
    const url = encodeURIComponent(this.shareUrl);
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, 'facebook-share', 'width=600,height=400');
  }

  sharePinterest() {
    const url = encodeURIComponent(this.shareUrl);
    const description = encodeURIComponent(this.photoTitle);
    window.open(`https://pinterest.com/pin/create/button/?url=${url}&description=${description}`, 'pinterest-share', 'width=600,height=400');
  }

  openModal() {
    showShareModal(this);
  }
}

function showShareModal(sharer) {
  const modal = document.createElement('div');
  modal.className = 'share-modal-overlay';
  modal.innerHTML = `
    <div class="share-modal">
      <button class="share-modal-close" aria-label="Close">&times;</button>
      <h3>Share Photo</h3>
      <div class="share-options">
        <button class="share-option copy-link" title="Copy link to clipboard">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
          </svg>
          <span>Copy Link</span>
        </button>
        <button class="share-option email" title="Share via email">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
          </svg>
          <span>Email</span>
        </button>
        <button class="share-option twitter" title="Share on Twitter">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2s9 5 20 5a9.5 9.5 0 00-9-5.5c4.75 2.25 7-7 7-7z"></path>
          </svg>
          <span>Twitter</span>
        </button>
        <button class="share-option facebook" title="Share on Facebook">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18 2h-3a6 6 0 00-6 6v3H7v4h2v8h4v-8h3l1-4h-4V8a1 1 0 011-1h3z"></path>
          </svg>
          <span>Facebook</span>
        </button>
        <button class="share-option pinterest" title="Share on Pinterest">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M8 12c0-1.657.895-3.1 2.236-3.9M12 12c0 1.657-.895 3.1-2.236 3.9" stroke="white" stroke-width="2" fill="none"></path>
          </svg>
          <span>Pinterest</span>
        </button>
      </div>
      <div class="share-preview">
        <strong>${sharer.photoTitle}</strong>
        <code>${sharer.shareUrl}</code>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Close button
  modal.querySelector('.share-modal-close').addEventListener('click', () => modal.remove());
  modal.addEventListener('click', (e) => {
    if (e.target === modal) modal.remove();
  });

  // Share option handlers
  modal.querySelector('.copy-link').addEventListener('click', () => {
    sharer.copyToClipboard();
    modal.remove();
  });
  modal.querySelector('.email').addEventListener('click', () => {
    sharer.shareEmail();
    modal.remove();
  });
  modal.querySelector('.twitter').addEventListener('click', () => {
    sharer.shareTwitter();
    modal.remove();
  });
  modal.querySelector('.facebook').addEventListener('click', () => {
    sharer.shareFacebook();
    modal.remove();
  });
  modal.querySelector('.pinterest').addEventListener('click', () => {
    sharer.sharePinterest();
    modal.remove();
  });
}

function showToast(message) {
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.add('show');
  });

  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 2000);
}

// Share button HTML snippet
function renderShareButton(photoId, eventSlug, photoTitle = 'Photo') {
  return `<button class="share-button" data-photo-id="${photoId}" data-event-slug="${eventSlug}" data-title="${photoTitle}" title="Share photo" aria-label="Share photo">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="18" cy="5" r="3"></circle>
      <circle cx="6" cy="12" r="3"></circle>
      <circle cx="18" cy="19" r="3"></circle>
      <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
      <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
    </svg>
  </button>`;
}

// Initialize share buttons
document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.share-button');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const photoId = btn.dataset.photoId;
    const eventSlug = btn.dataset.eventSlug;
    const title = btn.dataset.title || 'Photo';
    const sharer = new PhotoSharer(photoId, eventSlug, title);
    sharer.openModal();
  });
});
