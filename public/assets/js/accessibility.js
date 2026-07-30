/**
 * Accessibility Utilities: Keyboard Navigation, ARIA Support, Screen Reader Helpers
 * Ensures WCAG AA compliance across the gallery.
 */

const A11y = {
  /**
   * Initialize keyboard navigation for a modal/overlay
   * Close on Escape, trap focus within element
   * @param {HTMLElement} modal - The modal element
   * @param {HTMLElement} trigger - The element that opened the modal
   */
  initModalKeyboard(modal, trigger = null) {
    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    // Close on Escape
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        modal.classList.remove('active');
        if (trigger) trigger.focus();
      }
    };

    // Tab trap: keep focus within modal
    const handleTab = (e) => {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        }
      } else {
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        }
      }
    };

    modal.addEventListener('keydown', handleEscape);
    modal.addEventListener('keydown', handleTab);

    return {
      destroy() {
        modal.removeEventListener('keydown', handleEscape);
        modal.removeEventListener('keydown', handleTab);
      },
    };
  },

  /**
   * Add skip-to-content link for keyboard navigation
   * @param {string} contentId - ID of main content area
   */
  addSkipLink(contentId = 'photos') {
    const skipLink = document.createElement('a');
    skipLink.href = `#${contentId}`;
    skipLink.className = 'skip-to-content';
    skipLink.textContent = 'Skip to main content';
    skipLink.setAttribute('role', 'menuitem');

    document.body.insertBefore(skipLink, document.body.firstChild);
  },

  /**
   * Add ARIA labels to icon buttons
   * @param {HTMLElement} container - Container to scan
   */
  addIconButtonLabels(container) {
    const iconButtons = container.querySelectorAll('button.icon-button:not([aria-label])');
    const labelMap = {
      'heart': 'Add to favorites',
      'share': 'Share this photo',
      'close': 'Close',
      'prev': 'Previous',
      'next': 'Next',
      'download': 'Download',
    };

    iconButtons.forEach((btn) => {
      const icon = btn.querySelector('svg') || btn.textContent.trim();
      for (const [keyword, label] of Object.entries(labelMap)) {
        if (icon.includes && icon.includes(keyword)) {
          btn.setAttribute('aria-label', label);
          break;
        }
      }
    });
  },

  /**
   * Announce changes to screen readers
   * @param {string} message - Message to announce
   * @param {string} level - 'polite' or 'assertive' (default: 'polite')
   */
  announce(message, level = 'polite') {
    let liveRegion = document.getElementById('a11y-live-region');
    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.id = 'a11y-live-region';
      liveRegion.setAttribute('aria-live', level);
      liveRegion.setAttribute('aria-atomic', 'true');
      liveRegion.style.position = 'absolute';
      liveRegion.style.left = '-9999px';
      document.body.appendChild(liveRegion);
    }
    liveRegion.textContent = message;
  },

  /**
   * Add keyboard hints for interactive elements
   * Shows on first interaction, can be toggled
   */
  addKeyboardHints() {
    const hints = {
      lightbox: ['Arrows: Navigate', 'Esc: Close', 'H: Favorite', 'S: Share'].join(' • '),
      gallery: ['Tab: Navigate', 'Enter: Open', 'Esc: Close'].join(' • '),
    };

    // Add hints to lightbox if present
    const lightbox = document.getElementById('lightbox');
    if (lightbox && !lightbox.querySelector('.keyboard-hint')) {
      const hint = document.createElement('div');
      hint.className = 'keyboard-hint';
      hint.textContent = hints.lightbox;
      hint.setAttribute('role', 'status');
      lightbox.appendChild(hint);
    }
  },

  /**
   * Ensure all images have alt text
   * @param {HTMLElement} container - Container to scan
   */
  ensureImageAlt(container = document) {
    const images = container.querySelectorAll('img:not([alt])');
    images.forEach((img) => {
      const figcaption = img.nextElementSibling?.textContent;
      if (figcaption) {
        img.setAttribute('alt', figcaption);
      } else {
        img.setAttribute('alt', 'Photo');
      }
    });
  },

  /**
   * Test color contrast (WCAG AA: 4.5:1 for normal text, 3:1 for large text)
   * @param {string} foreground - Hex color
   * @param {string} background - Hex color
   * @returns {number} - Contrast ratio
   */
  getContrastRatio(foreground, background) {
    const getLuminance = (hex) => {
      const rgb = parseInt(hex.slice(1), 16);
      const r = (rgb >> 16) & 0xff;
      const g = (rgb >> 8) & 0xff;
      const b = (rgb >> 0) & 0xff;
      const [rs, gs, bs] = [r, g, b].map((x) => {
        x = x / 255;
        return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
    };

    const l1 = getLuminance(foreground);
    const l2 = getLuminance(background);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  },

  /**
   * Verify minimum contrast ratio
   * Logs warnings for elements with poor contrast
   * @param {HTMLElement} container - Container to scan
   */
  verifyContrast(container = document) {
    const elements = container.querySelectorAll('[style], h1, h2, h3, p, a, button, input, label');
    const issues = [];

    elements.forEach((el) => {
      const style = window.getComputedStyle(el);
      const fg = this.hexColor(style.color);
      const bg = this.hexColor(style.backgroundColor || '#ffffff');

      const ratio = this.getContrastRatio(fg, bg);
      const isLarge = parseInt(style.fontSize) >= 18;
      const minRatio = isLarge ? 3 : 4.5;

      if (ratio < minRatio) {
        issues.push({
          element: el.tagName,
          text: el.textContent.slice(0, 50),
          ratio: ratio.toFixed(2),
          required: minRatio,
        });
      }
    });

    if (issues.length > 0) {
      console.warn('Contrast issues found:', issues);
    }
    return issues;
  },

  /**
   * Convert CSS color to hex
   * @param {string} color - CSS color value
   * @returns {string} - Hex color
   */
  hexColor(color) {
    const ctx = document.createElement('canvas').getContext('2d');
    ctx.fillStyle = color;
    return ctx.fillStyle;
  },

  /**
   * Add role and aria-label to semantic elements
   * @param {HTMLElement} container - Container to enhance
   */
  enhanceSemantics(container = document) {
    // Ensure main content area is marked
    const main = container.querySelector('main');
    if (main && !main.getAttribute('role')) {
      main.setAttribute('role', 'main');
    }

    // Mark navigation areas
    const navs = container.querySelectorAll('nav:not([role])');
    navs.forEach((nav) => {
      nav.setAttribute('role', 'navigation');
      if (!nav.getAttribute('aria-label')) {
        nav.setAttribute('aria-label', 'Page navigation');
      }
    });

    // Mark filter forms
    const forms = container.querySelectorAll('form');
    forms.forEach((form) => {
      if (!form.getAttribute('aria-label') && !form.querySelector('legend')) {
        form.setAttribute('aria-label', 'Filter options');
      }
    });
  },

  /**
   * Initialize all accessibility features
   * Call this once on page load
   * @param {HTMLElement} container - Container to initialize
   */
  init(container = document) {
    this.addSkipLink('photos');
    this.ensureImageAlt(container);
    this.enhanceSemantics(container);
    this.addKeyboardHints();
    this.addIconButtonLabels(container);

    if (process.env.NODE_ENV === 'development') {
      this.verifyContrast(container);
    }
  },
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = A11y;
}
