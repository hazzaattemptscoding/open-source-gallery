-- Migration: advance credit (SQLite variant).
--
-- See 019_add_advance_credit.sql for the two load-bearing constraints: credit
-- is worthless until the Stripe webhook activates it, and spending is a single
-- conditional UPDATE so two simultaneous checkouts cannot both spend it.
--
-- SQLite differences:
--  * status is TEXT with a CHECK instead of ENUM.
--  * The balance CHECK constraints are inline, and SQLite enforces them the
--    same way MySQL does.
--  * ALTER TABLE cannot add a foreign key to the existing orders table, so
--    orders.credit_id carries no FK here. MySQL is the production target where
--    the real constraint applies; the column is only ever written by code that
--    has just read the credit row.

CREATE TABLE IF NOT EXISTS credits (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    code                  TEXT NOT NULL,
    email                 TEXT NOT NULL,
    currency              TEXT NOT NULL DEFAULT 'GBP',
    amount_pence          INTEGER NOT NULL,
    balance_pence         INTEGER NOT NULL DEFAULT 0,
    event_id              INTEGER NULL,
    status                TEXT NOT NULL DEFAULT 'pending'
                              CHECK (status IN ('pending','active','void')),
    stripe_checkout_id    TEXT NULL,
    stripe_payment_intent TEXT NULL,
    created_at            TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at          TEXT NULL,
    UNIQUE (code),
    UNIQUE (stripe_checkout_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CHECK (balance_pence >= 0),
    CHECK (balance_pence <= amount_pence)
);

CREATE INDEX IF NOT EXISTS idx_credits_email ON credits (email);
CREATE INDEX IF NOT EXISTS idx_credits_status ON credits (status);

CREATE TABLE IF NOT EXISTS credit_redemptions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    credit_id    INTEGER NOT NULL,
    order_id     INTEGER NOT NULL,
    amount_pence INTEGER NOT NULL,
    created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (credit_id, order_id),
    FOREIGN KEY (credit_id) REFERENCES credits(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_redemption_order ON credit_redemptions (order_id);

ALTER TABLE orders ADD COLUMN credit_applied_pence INTEGER NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN credit_id INTEGER NULL;
