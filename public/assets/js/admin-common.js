/**
 * Small behaviors shared across admin pages: confirm-before-submit on
 * delete forms (data-confirm="message" on the submit button) and
 * click-to-navigate on elements that act like links but aren't one
 * (data-href="/path" — used by clickable grid thumbnails).
 */

document.querySelectorAll('button[data-confirm]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.dataset.confirm)) {
      e.preventDefault();
    }
  });
});

document.querySelectorAll('[data-href]').forEach(el => {
  el.addEventListener('click', () => {
    window.location.href = el.dataset.href;
  });
});
