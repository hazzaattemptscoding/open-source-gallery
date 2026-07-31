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

/**
 * The behaviours below replace inline onclick/onchange attributes that the
 * app's Content-Security-Policy (default-src 'self') refused to execute. Each
 * is declarative so no admin view needs a script block of its own.
 */

// Confirm-before-submit on a whole form (data-confirm on the <form>), the
// counterpart to the button-level data-confirm above.
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', (e) => {
    if (!confirm(form.dataset.confirm)) {
      e.preventDefault();
    }
  });
});

// Selects that submit their own form on change (role pickers, filters).
document.querySelectorAll('select[data-submit-on-change]').forEach(select => {
  select.addEventListener('change', () => select.form && select.form.submit());
});

// Open a URL in a sized popup window (the customization live preview).
document.querySelectorAll('[data-popup]').forEach(el => {
  el.addEventListener('click', () => {
    window.open(el.dataset.popup, el.dataset.popupName || '_blank',
                el.dataset.popupSize || 'width=1200,height=800');
  });
});

// Progressive disclosure: a <select> reveals the option group matching its
// value, hiding its siblings. Used by the bulk-operations form.
document.querySelectorAll('select[data-reveal-group]').forEach(select => {
  const groups = select.dataset.revealGroup.split(',').map(s => s.trim());
  const sync = () => {
    groups.forEach(id => {
      const el = document.getElementById(id + '-options');
      if (el) el.classList.toggle('active', id === select.value);
    });
  };
  select.addEventListener('change', sync);
  sync();
});
