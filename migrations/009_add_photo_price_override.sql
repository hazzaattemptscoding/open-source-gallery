-- Per-photo price override: allows setting price_pence on individual photos
-- distinct from the event bundle price. NULL means "inherit event price."
-- This supports the "mark individual photos as premium" feature without
-- requiring separate bundle pricing logic.


ALTER TABLE photos ADD COLUMN price_pence INT UNSIGNED NULL COMMENT 'Per-photo price override; NULL inherits event price_single_pence';
