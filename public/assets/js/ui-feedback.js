/**
 * UI Feedback System: Toasts, Progress Bars, and Form Validation
 * Provides reusable utilities for user feedback across the gallery.
 */

const UIFeedback = {
  /**
   * Show a toast notification
   * @param {string} message - The message to display
   * @param {string} type - 'success', 'error', 'info' (default: 'info')
   * @param {number} duration - How long to show (ms, default: 3000)
   */
  showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideUpIn 400ms cubic-bezier(0.23, 1, 0.32, 1) reverse';
      setTimeout(() => toast.remove(), 400);
    }, duration);
  },

  /**
   * Show a progress bar at the top of the page
   * @param {number} percent - Progress percentage (0-100)
   */
  setProgress(percent) {
    let progressBar = document.getElementById('progressBar');
    if (!progressBar) {
      progressBar = document.createElement('div');
      progressBar.id = 'progressBar';
      progressBar.className = 'progress-bar';
      document.body.appendChild(progressBar);
    }
    progressBar.style.width = `${Math.min(percent, 100)}%`;
  },

  /**
   * Hide the progress bar
   */
  hideProgress() {
    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
      progressBar.style.width = '0';
      setTimeout(() => progressBar.remove(), 300);
    }
  },

  /**
   * Start showing loading skeletons in a container
   * @param {HTMLElement} container - Container to fill with skeletons
   * @param {number} count - How many skeletons to show
   * @param {string} type - 'photo' or 'text'
   */
  showSkeletons(container, count = 6, type = 'photo') {
    container.innerHTML = '';
    for (let i = 0; i < count; i++) {
      const skeleton = document.createElement('div');
      if (type === 'photo') {
        skeleton.className = 'skeleton skeleton-photo';
      } else {
        skeleton.className = 'skeleton skeleton-text';
      }
      container.appendChild(skeleton);
    }
  },

  /**
   * Validate a form and show inline errors
   * @param {HTMLFormElement} form - The form to validate
   * @param {Object} rules - Validation rules per field
   * @returns {boolean} - True if valid, false otherwise
   */
  validateForm(form, rules = {}) {
    let isValid = true;
    const fields = form.querySelectorAll('[name]');

    fields.forEach(field => {
      const fieldName = field.getAttribute('name');
      const rule = rules[fieldName];
      let error = null;

      // Required validation
      if (rule && rule.required && !field.value.trim()) {
        error = rule.requiredMessage || `${fieldName} is required`;
      }

      // Email validation
      if (rule && rule.email && field.value.trim()) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(field.value)) {
          error = rule.emailMessage || 'Invalid email address';
        }
      }

      // Min length validation
      if (rule && rule.minLength && field.value.trim().length < rule.minLength) {
        error = rule.minLengthMessage || `Must be at least ${rule.minLength} characters`;
      }

      // Display error or success
      const existingError = field.parentElement.querySelector('.form-error');
      if (existingError) existingError.remove();

      if (error) {
        isValid = false;
        const errorEl = document.createElement('div');
        errorEl.className = 'form-error';
        errorEl.textContent = error;
        field.parentElement.appendChild(errorEl);
        field.setAttribute('aria-invalid', 'true');
      } else {
        field.setAttribute('aria-invalid', 'false');
      }
    });

    return isValid;
  },

  /**
   * Clear all error messages from a form
   * @param {HTMLFormElement} form - The form to clear
   */
  clearFormErrors(form) {
    const errors = form.querySelectorAll('.form-error');
    errors.forEach(error => error.remove());
    const fields = form.querySelectorAll('[name]');
    fields.forEach(field => field.setAttribute('aria-invalid', 'false'));
  },

  /**
   * Add real-time validation to form fields
   * @param {HTMLFormElement} form - The form to add validation to
   * @param {Object} rules - Validation rules
   */
  enableRealtimeValidation(form, rules = {}) {
    const fields = form.querySelectorAll('[name]');
    fields.forEach(field => {
      field.addEventListener('blur', () => {
        const fieldName = field.getAttribute('name');
        const rule = rules[fieldName];
        if (!rule) return;

        let error = null;
        if (rule.required && !field.value.trim()) {
          error = rule.requiredMessage || `${fieldName} is required`;
        }
        if (rule.email && field.value.trim()) {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(field.value)) {
            error = rule.emailMessage || 'Invalid email address';
          }
        }
        if (rule.minLength && field.value.trim().length < rule.minLength) {
          error = rule.minLengthMessage || `Must be at least ${rule.minLength} characters`;
        }

        const existingError = field.parentElement.querySelector('.form-error');
        if (existingError) existingError.remove();

        if (error) {
          const errorEl = document.createElement('div');
          errorEl.className = 'form-error';
          errorEl.textContent = error;
          field.parentElement.appendChild(errorEl);
          field.setAttribute('aria-invalid', 'true');
        } else {
          field.setAttribute('aria-invalid', 'false');
        }
      });
    });
  },

  /**
   * Auto-focus first invalid field or first field in form
   * @param {HTMLFormElement} form - The form
   */
  autoFocusForm(form) {
    const invalidField = form.querySelector('[aria-invalid="true"]');
    if (invalidField) {
      invalidField.focus();
    } else {
      const firstField = form.querySelector('[name]');
      if (firstField) firstField.focus();
    }
  },

  /**
   * Add optimistic update: remove item instantly with undo option
   * @param {HTMLElement} element - The element to remove
   * @param {Function} undoCallback - Callback to restore if user clicks undo
   */
  optimisticRemove(element, undoCallback) {
    const clone = element.cloneNode(true);
    element.style.opacity = '0';
    element.style.transition = 'opacity 200ms ease-out';

    setTimeout(() => {
      const undo = document.createElement('button');
      undo.className = 'button';
      undo.textContent = 'Undo';
      undo.type = 'button';
      undo.style.marginLeft = '8px';

      undo.addEventListener('click', () => {
        element.style.opacity = '1';
        undo.remove();
        undoCallback();
      });

      const undoContainer = document.createElement('div');
      undoContainer.style.marginTop = '8px';
      undoContainer.appendChild(undo);

      const parent = element.parentElement;
      parent.insertBefore(undoContainer, element.nextSibling);

      setTimeout(() => {
        element.remove();
        setTimeout(() => {
          undo.remove();
          undoContainer.remove();
        }, 5000);
      }, 5000);
    }, 200);
  },
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = UIFeedback;
}
