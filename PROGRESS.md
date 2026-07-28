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
