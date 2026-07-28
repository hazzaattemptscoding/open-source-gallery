# Design & Gallery Restructuring Progress

## Completed
- [x] Install design skills: design-taste-frontend, minimalist-ui, impeccable
- [x] Replace Podium Ink (purple/gold) theme with white/black minimal aesthetic
  - Updated CLAUDE.md Design section
  - Rewrote podium-ink.css (white, black, generous whitespace, full-bleed)
  - Rewrote admin.css (removed all purple/gold references)
  - Updated upload page styling (clean bordered box instead of dashed)
  - All admin buttons changed to black/white
  - Table headers now black instead of purple
  
## In Progress
- [ ] Gallery structure restructuring:
  - [ ] Update event.php to hero-first layout
  - [ ] Add conditional filter dropdowns (only render if data exists)
  - [ ] Add search functionality (searches name/number/class)
  - [ ] Implement URL-based filter state (?class=cadet&date=2025-06-23)
  - [ ] Add tap-to-enlarge for watermarked previews
  - [ ] Add proper empty state with reset button

- [ ] Image tiering strategy:
  - [ ] Generate 1600px on upload
  - [ ] Cron job regenerates to 800px at 7+ days old
  - [ ] Full unwatermarked reserved for post-purchase only

## Pending
- [ ] Update API endpoints for new filter schema
- [ ] Test gallery with filters and search
- [ ] Verify responsive behavior on mobile
- [ ] Test empty states and edge cases
- [ ] Push changes to origin/claude/plugin-skill-setup-y9v6kx
- [ ] Test complete end-to-end workflow

## Decisions Made
- White/black minimal aesthetic matches premium editorial photography aesthetic
- One solid look, not style-preset switching (reserved for later)
- Filter dropdowns conditional to reduce UI clutter when data unavailable
- Search separate from filters, more discoverable as prominent hero element
- Image tiering preserves storage (800px copies after 7 days) while maintaining quality
