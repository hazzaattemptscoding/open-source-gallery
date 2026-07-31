/**
 * Floating Progress Widget
 * Collapsible bubble that tracks upload/download progress.
 * Lives in bottom-right corner, follows user, can be dragged and dismissed.
 */

class ProgressWidget {
  constructor(title = 'Upload') {
    this.title = title;
    this.isVisible = false;
    this.isExpanded = false;
    this.files = [];
    this.currentProgress = 0;
    this.totalProgress = 0;
    this.speed = 0;
    this.startTime = null;
    this.dragStartX = 0;
    this.dragStartY = 0;
    this.isDragging = false;
    this.position = { x: 0, y: 0 };

    this.create();
  }

  create() {
    const container = document.createElement('div');
    container.className = 'progress-widget';
    container.innerHTML = `
      <div class="progress-widget-bubble" role="button" tabindex="0" aria-label="Toggle progress widget">
        <div class="progress-widget-badge">
          <svg class="progress-widget-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3v-8"></path>
          </svg>
          <div class="progress-widget-percentage">0%</div>
        </div>
      </div>

      <div class="progress-widget-expanded" style="display: none;">
        <div class="progress-widget-header">
          <h3 class="progress-widget-title">${escapeHtml(this.title)}</h3>
          <button class="progress-widget-close" aria-label="Close progress widget">&times;</button>
        </div>
        <div class="progress-widget-content">
          <div class="progress-widget-files"></div>
        </div>
        <div class="progress-widget-stats">
          <div class="progress-widget-speed">—</div>
          <div class="progress-widget-time">—</div>
        </div>
      </div>
    `;

    this.container = container;
    this.bubble = container.querySelector('.progress-widget-bubble');
    this.expanded = container.querySelector('.progress-widget-expanded');
    this.closeBtn = container.querySelector('.progress-widget-close');
    this.filesContainer = container.querySelector('.progress-widget-files');
    this.percentageEl = container.querySelector('.progress-widget-percentage');
    this.speedEl = container.querySelector('.progress-widget-speed');
    this.timeEl = container.querySelector('.progress-widget-time');

    this.attachEventListeners();
  }

  attachEventListeners() {
    this.bubble.addEventListener('click', () => this.toggleExpanded());
    this.bubble.addEventListener('mousedown', (e) => this.startDrag(e));
    this.closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      this.hide();
    });

    document.addEventListener('mousemove', (e) => this.drag(e));
    document.addEventListener('mouseup', () => this.endDrag());

    // Keyboard support
    this.bubble.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.toggleExpanded();
      }
      if (e.key === 'Escape') {
        this.hide();
      }
    });
  }

  show(title = null) {
    if (title) this.title = title;
    if (!this.isVisible) {
      document.body.appendChild(this.container);
      this.isVisible = true;
      this.startTime = Date.now();
    }
  }

  hide() {
    if (this.container.parentNode) {
      this.container.parentNode.removeChild(this.container);
    }
    this.isVisible = false;
    this.isExpanded = false;
  }

  toggleExpanded() {
    this.isExpanded = !this.isExpanded;
    this.expanded.style.display = this.isExpanded ? 'block' : 'none';
    if (this.isExpanded) {
      this.bubble.setAttribute('aria-expanded', 'true');
    } else {
      this.bubble.setAttribute('aria-expanded', 'false');
    }
  }

  addFile(name, size) {
    this.files.push({ name, size, progress: 0 });
    this.renderFiles();
  }

  updateProgress(fileIndex, progress) {
    if (this.files[fileIndex]) {
      this.files[fileIndex].progress = Math.min(100, Math.max(0, progress));
      this.renderFiles();
      this.updateOverallProgress();
    }
  }

  updateOverallProgress() {
    if (this.files.length === 0) {
      this.currentProgress = 0;
    } else {
      const total = this.files.reduce((sum, f) => sum + f.progress, 0);
      this.currentProgress = Math.round(total / this.files.length);
    }

    this.percentageEl.textContent = `${this.currentProgress}%`;
    this.updateSpeed();
  }

  updateSpeed() {
    if (!this.startTime || this.currentProgress === 0) {
      this.speedEl.textContent = '—';
      this.timeEl.textContent = '—';
      return;
    }

    const elapsed = (Date.now() - this.startTime) / 1000;
    const totalSize = this.files.reduce((sum, f) => sum + f.size, 0);
    const uploadedSize = (totalSize * this.currentProgress) / 100;
    const speedMBps = (uploadedSize / (1024 * 1024)) / elapsed;

    this.speedEl.textContent = speedMBps > 0 ? `${speedMBps.toFixed(1)} MB/s` : '—';

    if (speedMBps > 0) {
      const remainingSize = totalSize - uploadedSize;
      const remainingSeconds = (remainingSize / (1024 * 1024)) / speedMBps;
      const remaining = this.formatTime(remainingSeconds);
      this.timeEl.textContent = remaining;
    } else {
      this.timeEl.textContent = '—';
    }
  }

  formatTime(seconds) {
    if (seconds < 60) return `${Math.ceil(seconds)}s`;
    const minutes = Math.floor(seconds / 60);
    const secs = Math.ceil(seconds % 60);
    return `${minutes}m ${secs}s`;
  }

  renderFiles() {
    this.filesContainer.innerHTML = '';
    this.files.forEach((file) => {
      const fileEl = document.createElement('div');
      fileEl.className = 'progress-widget-file';
      fileEl.innerHTML = `
        <div class="progress-widget-filename" title="${escapeHtml(file.name)}">
          ${escapeHtml(file.name)}
        </div>
        <div class="progress-widget-filesize">
          ${this.formatSize(file.size)} • ${file.progress}%
        </div>
        <div class="progress-widget-bar-container">
          <div class="progress-widget-bar ${file.progress < 100 ? 'active' : ''}" style="width: ${file.progress}%"></div>
        </div>
      `;
      this.filesContainer.appendChild(fileEl);
    });
  }

  formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  startDrag(e) {
    this.isDragging = true;
    this.dragStartX = e.clientX - this.position.x;
    this.dragStartY = e.clientY - this.position.y;
    this.container.classList.add('dragging');
  }

  drag(e) {
    if (!this.isDragging) return;

    const newX = e.clientX - this.dragStartX;
    const newY = e.clientY - this.dragStartY;

    // Keep within viewport
    const maxX = window.innerWidth - this.container.offsetWidth;
    const maxY = window.innerHeight - this.container.offsetHeight;

    this.position.x = Math.max(0, Math.min(newX, maxX));
    this.position.y = Math.max(0, Math.min(newY, maxY));

    this.container.style.left = this.position.x + 'px';
    this.container.style.right = 'auto';
    this.container.style.bottom = 'auto';
    this.container.style.top = this.position.y + 'px';
  }

  endDrag() {
    this.isDragging = false;
    this.container.classList.remove('dragging');
  }

  clear() {
    this.files = [];
    this.currentProgress = 0;
    this.percentageEl.textContent = '0%';
    this.speedEl.textContent = '—';
    this.timeEl.textContent = '—';
    this.filesContainer.innerHTML = '';
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Global instance (for easy access across the site)
window.progressWidget = null;

function getProgressWidget(title = 'Upload') {
  if (!window.progressWidget) {
    window.progressWidget = new ProgressWidget(title);
  }
  return window.progressWidget;
}
