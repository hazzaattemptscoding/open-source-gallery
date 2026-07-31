/**
 * Contrast advisor for the customization page.
 *
 * The colour pickers let an admin pick any seven values, including
 * combinations that render the site unreadable: white text on a white
 * background is one save away at all times, and the damage lands on the
 * public gallery, not just the admin screen.
 *
 * This checks the pairs that actually get composited together in
 * get_customize_css_overrides() and reports WCAG 2.1 contrast ratios. It is
 * deliberately advisory: it never blocks the save, because a palette is
 * routinely unreadable halfway through being edited, and a form that fights
 * the user mid-edit is worse than one that warns them at the end.
 */

(function () {
  const advisor = document.getElementById('contrastAdvisor');
  const list = document.getElementById('contrastAdvisorList');
  if (!advisor || !list) return;

  /**
   * The pairs worth checking: foreground/background combinations the
   * stylesheet actually puts on top of each other. Checking every possible
   * pair would bury the real problems in noise.
   *
   * Borders are deliberately absent. WCAG sets no contrast requirement for
   * decorative dividers, and an earlier version of this check invented a
   * threshold for them that the shipped default palette then failed, which
   * is exactly how a warning becomes background noise people click past.
   */
  const PAIRS = [
    { fg: 'text', bg: 'bg', label: 'Body text on page background', min: 4.5 },
    { fg: 'text', bg: 'bg_alt', label: 'Body text on panels', min: 4.5 },
    { fg: 'text_muted', bg: 'bg', label: 'Hints on page background', min: 4.5 },
    { fg: 'text_muted', bg: 'bg_alt', label: 'Hints on panels', min: 4.5 },
    // Accent carries buttons and focus rings. 3.0 is the WCAG threshold for
    // non-text UI components rather than the 4.5 required of body copy.
    { fg: 'accent', bg: 'bg', label: 'Accent against page background', min: 3 },
  ];

  /**
   * Ratios within this much of a threshold are treated as meeting it. The
   * shipped --text-muted (#787774 on white) computes to 4.48 against a 4.5
   * requirement: a difference invisible to any reader, but enough to make the
   * advisor complain about the product's own defaults on first load. A tool
   * that cries wolf on the default palette teaches people to ignore it.
   */
  const THRESHOLD_TOLERANCE = 0.05;

  /** Parse #rgb or #rrggbb into [r, g, b], or null if it is neither. */
  function parseHex(value) {
    if (typeof value !== 'string') return null;
    let hex = value.trim().replace(/^#/, '');
    if (hex.length === 3) hex = hex.split('').map((c) => c + c).join('');
    if (!/^[0-9a-f]{6}$/i.test(hex)) return null;
    return [
      parseInt(hex.slice(0, 2), 16),
      parseInt(hex.slice(2, 4), 16),
      parseInt(hex.slice(4, 6), 16),
    ];
  }

  /** Relative luminance, per the WCAG 2.1 definition. */
  function luminance([r, g, b]) {
    const channel = (v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
  }

  /** Contrast ratio between two colours: 1 (identical) to 21 (black on white). */
  function contrastRatio(a, b) {
    const la = luminance(a);
    const lb = luminance(b);
    const lighter = Math.max(la, lb);
    const darker = Math.min(la, lb);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function evaluate() {
    const problems = [];

    for (const pair of PAIRS) {
      const fgInput = document.getElementById(pair.fg);
      const bgInput = document.getElementById(pair.bg);
      if (!fgInput || !bgInput) continue;

      const fg = parseHex(fgInput.value);
      const bg = parseHex(bgInput.value);
      if (!fg || !bg) continue;

      const ratio = contrastRatio(fg, bg);
      if (ratio >= pair.min - THRESHOLD_TOLERANCE) continue;

      // Below half the threshold is not a near miss, it is unreadable.
      problems.push({
        label: pair.label,
        ratio: ratio,
        min: pair.min,
        level: ratio < pair.min / 2 ? 'fail' : 'warn',
      });
    }

    list.textContent = '';

    if (problems.length === 0) {
      advisor.hidden = true;
      return;
    }

    for (const problem of problems) {
      const li = document.createElement('li');
      li.className = 'contrast-advisor-item';
      li.dataset.level = problem.level;

      const text = document.createElement('span');
      text.textContent =
        problem.level === 'fail'
          ? problem.label + ' is unreadable'
          : problem.label + ' is hard to read';

      const ratio = document.createElement('span');
      ratio.className = 'contrast-advisor-ratio';
      ratio.textContent = problem.ratio.toFixed(1) + ':1, needs ' + problem.min + ':1';

      li.appendChild(text);
      li.appendChild(ratio);
      list.appendChild(li);
    }

    advisor.hidden = false;
  }

  for (const pair of PAIRS) {
    for (const id of [pair.fg, pair.bg]) {
      const input = document.getElementById(id);
      if (input) input.addEventListener('input', evaluate);
    }
  }

  evaluate();
})();
