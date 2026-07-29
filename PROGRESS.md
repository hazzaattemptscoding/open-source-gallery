# Implementation Progress

## Branch: claude/plugin-skill-setup-y9v6kx

### FEATURE A: Remote Fulfillment / NAS Storage Mode

**Status**: Planning → Implementation

**Scope**: Optional advanced feature for users with home NAS + always-on poller machine. Does not affect default shared-hosting setup.

**Architecture**:
- **Config**: Add `storage_mode` ('local' or 'remote-nas') to config
- **Jobs table**: Extend with fulfillment job type
- **API endpoint**: `/admin/api/fulfillment` - authenticated, returns pending jobs for poller
- **Poller script**: Standalone PHP that runs on always-on machine, polls every 60-90s
- **WoL support**: phpseclib for Wake-on-LAN magic packet
- **Temp storage**: Non-web-accessible directory, auto-cleanup after download or 72h
- **Alerting**: Email admin if job stalls >15 min
- **UX**: Update checkout success page copy based on storage mode

**Implementation Order**:
1. Extend config.example.php with storage_mode
2. Create fulfillment.php library
3. Add API endpoint for poller
4. Create poller.php script + documentation
5. Extend cron job cleanup
6. Update checkout success page
7. Add setup wizard option for storage mode

---

### FEATURE B: First-Run Setup Wizard

**Status**: Planning → Implementation

**Scope**: Replace basic one-page setup with multi-step guided wizard, phone-setup style. Premium design via impeccable/taste-skill.

**Steps**:
1. **Admin account** (mandatory) - email, password, confirm
2. **Business details** (mandatory) - name, contact email, currency
3. **Email setup** (skippable) - from address, SMTP provider quick-picks (Gmail/Outlook/IONOS/Custom)
4. **Stripe keys** (skippable) - publishable + secret, with dashboard link
5. **Storage mode** (skippable) - local vs remote-nas explanation, defaults local
6. **Admin mode** (skippable) - local vs remote toggle, defaults local
7. **Summary** - confirms setup, lists skipped items

**Persisted checklist**: Dashboard/settings shows "Setup incomplete" with direct links to finish each skipped item.

**Implementation Order**:
1. Create multi-step wizard controller
2. Build wizard UI views (one per step) with premium design
3. Store wizard progress in session
4. Add config write/read layer (persist to config.php)
5. Create setup status helper library
6. Add checklist to dashboard
7. Add settings page link to resume wizard

---

## Files to Create/Modify

### New Files
- `/app/lib/fulfillment.php` - Fulfillment job logic
- `/app/controllers/admin/api_fulfillment.php` - Poller API endpoint
- `/tools/poller.php` - Standalone poller script (for user's NAS machine)
- `/app/lib/setup.php` - Setup state + completion tracking
- `/app/views/admin/setup_wizard.php` - Multi-step wizard template
- `/docs/SETUP-WIZARD.md` - Setup wizard UX documentation
- `/docs/NAS-FULFILLMENT.md` - NAS mode architecture + setup guide

### Modified Files
- `config/config.example.php` - Add storage_mode
- `app/controllers/admin/setup.php` - Replace with wizard controller
- `app/views/admin/setup.php` - Replace with wizard template
- `public/index.php` - Add `/admin/api/fulfillment` route
- `app/lib/cron.php` - Add fulfillment timeout alerting + temp file cleanup
- `app/controllers/public/checkout.php` - Add fulfillment job on order completion
- `app/controllers/admin/dashboard.php` - Add setup checklist widget
- `migrations/001_initial_schema.sql` - Add fulfillment tracking columns to jobs table

---

## Design Decisions

### STORAGE_MODE
- **Default**: 'local' (current behavior, no changes needed)
- **Alternative**: 'remote-nas' (advanced, opt-in)
- **Rationale**: Most self-hosters won't have NAS + always-on poller. Don't expose this complexity by default. Keep it in config, not UI-driven, so it's intentional.

### Fulfillment vs Jobs Table
- Reuse existing `jobs` table with new job type: 'fulfillment'
- Add columns: `nas_mac_address`, `wol_sent_at`, `fulfilled_at`, `alert_sent_at`
- Rationale: One job queue pattern, consistent with existing cron worker

### Wizard vs One-Page Setup
- Replace `/admin/setup` entirely with stateful wizard
- Store step progress in session (temp) and settings table (permanent)
- Rationale: Premium UX, guided onboarding, reduces config mistakes

### Setup Checklist
- Show on dashboard as prominent widget until complete
- Survives login (stored in settings table)
- Allows skipping non-critical steps, but surface them visibly
- Rationale: Users won't silently miss required config (SMTP, Stripe)

---

## Progress

- [ ] FEATURE A: Remote Fulfillment / NAS Storage Mode
  - [ ] Extend config with storage_mode
  - [ ] Create fulfillment.php library
  - [ ] Add API endpoint
  - [ ] Create poller script
  - [ ] Extend cron cleanup
  - [ ] Update checkout success
  - [ ] Add wizard option

- [ ] FEATURE B: First-Run Setup Wizard
  - [ ] Create multi-step wizard controller
  - [ ] Build wizard UI (7 steps)
  - [ ] Config write/read layer
  - [ ] Setup status helper
  - [ ] Dashboard checklist
  - [ ] Settings resume link

- [ ] Testing
- [ ] PR ready (not merged to main)
