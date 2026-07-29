# Design & Gallery Restructuring Progress

## Completed
- [x] Install design skills: design-taste-frontend, minimalist-ui, impeccable
- [x] Replace Podium Ink (purple/gold) theme with white/black minimal aesthetic
  - Updated CLAUDE.md Design section to "premium editorial aesthetic"
  - Rewrote podium-ink.css (white, black, generous whitespace, full-bleed images)
  - Rewrote admin.css (removed all purple/gold references)
  - Updated upload page styling (clean bordered box instead of dashed border)
  - All admin buttons changed to black/white palette
  - Table headers now black instead of purple
  - Input fields and form elements match new minimal style
  
- [x] Gallery structure restructuring:
  - [x] Updated event.php to hero-first layout (full-screen, admin-selectable cover photo)
  - [x] Added conditional filter dropdowns (only render if event has data for that field)
  - [x] Added search functionality (searches name/number/class via client-side filtering)
  - [x] Implemented URL-based filter state (already supported by architecture)
  - [x] Added prominent search box on hero above filter bar
  - [x] Added proper empty state with reset button for zero-result filters
  - [x] Photo grid items include tag data attributes for search

- [x] Image tiering strategy:
  - [x] Initial generation: 400px, 800px, 1600px with watermarks
  - [x] Cron job automatically queued for 7 days after photo goes live
  - [x] Cleanup job deletes 1600px version (keeps 400/800 for responsive display)
  - [x] Reduces storage while maintaining quality and gallery responsiveness
  - [x] Full unwatermarked original always preserved for post-purchase delivery

## Remaining Tasks (From User Brief)
- [ ] Test filtering with real data (date, class, championship, track, country filters)
- [ ] Verify search functionality across different tag combinations
- [ ] Test responsive design on mobile devices
- [ ] Test empty states and edge cases
- [ ] Push completed changes to origin/claude/plugin-skill-setup-y9v6kx
- [ ] Verify cron image tiering job executes correctly
- [ ] End-to-end workflow test: upload → derivative generation → 7-day cleanup

## Decisions & Notes
- White/black minimal aesthetic matches premium editorial photography aesthetic
- One solid look, no style-preset switching system (reserved for later stage)
- Filter dropdowns are conditional: only shown if event has corresponding data
- Search is separate from filters, positioned prominently for discoverability
- Image tiering: 1600px serves high-quality gallery views, auto-downgrades after 7 days
- Watermarks applied only to 400px+ sizes per existing settings (watermark_min_width)
- Photo tags (kart, driver, class) fetched via GROUP_CONCAT for each photo
- Search works client-side by matching search input against tag data attributes
- API endpoint (/api/photos) reuses same fetch_gallery_media function, ensures consistency

---

## Comprehensive Design Enhancement Pass (In Progress)

### Audit Findings

**Current State:** White/black palette applied, but needs:
1. **Typography:** Generic `-apple-system` stack instead of premium fonts
2. **Spacing:** Ad-hoc margins/padding, not consistent whitespace scale
3. **Micro-interactions:** No hover states, button feedback, or animations
4. **Motion:** No scroll-entry animations or visual depth
5. **Copy:** User-facing text needs stop-slop review

### Enhancement Checklist

**Typography (minimalist-ui + impeccable):**
- [ ] Implement 'Geist Sans' fallback chain (SF Pro Display → Helvetica Neue → system)
- [ ] Implement serif alternative for headings ('Instrument Serif' or 'Newsreader')
- [ ] Establish consistent font-weight hierarchy (400 body, 500 labels, 600 headings)
- [ ] Fix line-height (1.6 for body, 1.2 for headings, 1.1 for serif editorial)
- [ ] Off-black text (#111111) instead of pure white on black
- [ ] Muted gray secondary text (#787774)

**Spacing (impeccable + minimalist-ui):**
- [ ] Define spacing scale: 8px, 16px, 24px, 32px, 48px, 64px base units
- [ ] 24-40px padding on cards
- [ ] 32-64px vertical spacing between sections
- [ ] Consistent input/button sizing (0.75rem padding baseline)
- [ ] Max-width constraints (max-w-4xl / 56 rem for content)

**Micro-interactions (emil-design-eng):**
- [ ] Button `:active` state: `transform: scale(0.98)` transition 160ms ease-out
- [ ] Hover states: card shadow lift `0 2px 8px rgba(0,0,0,0.04)` over 200ms
- [ ] Link hover: underline or subtle color change, no sudden jumps
- [ ] Form inputs: focus ring on `-1px outline` (maintain current), tighten it
- [ ] Dropdowns/selects: smooth open/close animation 150-250ms ease-out
- [ ] Tooltips: skip delay on subsequent hovers
- [ ] No animation on keyboard-triggered actions (prefer instant feedback)

**Motion (emil-design-eng):**
- [ ] Scroll-entry animations: `IntersectionObserver` for fade + translateY
- [ ] Stagger delays on list items: `calc(var(--index) * 80ms)`
- [ ] Modal/drawer open: 200-500ms ease-out
- [ ] Toast notifications: enter from edge with `translateY(100%)` + opacity
- [ ] Easing curve: Use `cubic-bezier(0.16, 1, 0.3, 1)` for snappy UI
- [ ] Disable animations with `prefers-reduced-motion` media query

**Copy (stop-slop):**
- [ ] Remove AI clichés: "Elevate", "Seamless", "Unleash", "Next-Gen", "Delve"
- [ ] Cut filler: "Simply", "Actually", "Basically", "Really"
- [ ] Active voice: Check all copy for passive constructions
- [ ] Specific language: No vague "the reasons are structural"
- [ ] Cut em-dashes throughout
- [ ] Review all button labels, error messages, hints

### CSS Enhancement: Completed ✓

**podium-ink.css rewrite:**
- [x] Light theme: White (#ffffff) → Bone (#f7f6f3) → Off-black text (#111111)
- [x] Premium typography: Geist Sans + Instrument Serif with proper hierarchy
- [x] Spacing scale: 8px-64px grid with CSS variables (--sp-1 through --sp-6)
- [x] Micro-interactions:
  - Button press: `scale(0.98)` on `:active`, 160ms ease-out
  - Hover states: Card shadows 0 2px 8px rgba(0,0,0,0.06) over 200ms
  - Link hover: `opacity: 0.7` over 200ms
  - Add-to-cart: Scale lift on hover with box-shadow
- [x] Animations:
  - Auth card: fadeInUp 500ms on page load
  - Panels: slideIn 400ms with stagger
  - Lightbox: fadeIn 300ms + zoom
  - Tab transitions: fadeIn 200ms on active
- [x] Form styling: Improved focus states with outline + box-shadow
- [x] Error states: Proper muted pastels (FDEBEC for red, etc)
- [x] Tables: Professional header styling with uppercase labels
- [x] Accessibility: prefers-reduced-motion media query
- [x] Responsive: Mobile-first adjustments for all components

**CSS Defaults:**
- Easing curves: cubic-bezier(0.23, 1, 0.32, 1) for snappy UI
- No animations on keyboard actions (instant feedback)
- All transitions on transform/opacity only (GPU-accelerated)
- Border radius: 3-4px (crisp, not rounded)
- Font smoothing: antialiased + grayscale

### Pages: Before/After Status

#### Public Pages  
- Home: [in-progress] CSS applied, needs HTML review for copy
- Event: [in-progress] CSS applied, hero gradient implemented
- Search: [in-progress] CSS applied to components
- Cart: [in-progress] CSS applied, button states refined
- Checkout Success: [pending] Copy review
- 404: [pending] Styling

#### Admin Pages
- Login: [in-progress] Auth card animation, form styling
- Setup: [in-progress] Auth card styles
- Dashboard: [in-progress] Panel animations, list styling
- Settings: [pending] Form refinement
- Upload: [pending] Drop zone styling (dashed border → clean box)
- All data tables: [in-progress] Table header styling applied
- Photo grids: [in-progress] Hover animations implemented
- Buttons: [in-progress] All button states updated
