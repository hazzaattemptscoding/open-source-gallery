-- Migration: advance credit. Buy before the event, spend when the gallery opens.
--
-- The mechanic, and why it is worth the care
-- ------------------------------------------
-- A parent pays on race day, while they are still at the circuit and still
-- excited, and redeems it days later when the photos appear. It removes the
-- "I'll look later" drop-off, which is where most of these galleries lose their
-- sales.
--
-- It also means this application now stores monetary value, which every table
-- before it did not. An order is a record of a completed transaction; a credit
-- is a balance somebody can spend. That changes what the schema has to
-- guarantee, and the two constraints below are the load-bearing ones.
--
-- Constraint 1: credit is worthless until Stripe says the money arrived.
-- A row is created as 'pending' when checkout starts and only becomes 'active',
-- with a spendable balance, when the webhook confirms payment. An abandoned
-- credit purchase therefore leaves a pending row that can never be spent. The
-- balance column starts at zero for exactly this reason: even a bug that
-- treated a pending credit as active would find nothing in it.
--
-- Constraint 2: a credit cannot be spent twice.
-- Spending is a single conditional UPDATE that decrements balance_pence only
-- while enough remains (see spend_credit() in app/lib/credit.php). Two
-- simultaneous checkouts race that UPDATE; exactly one changes a row and the
-- other sees zero affected rows and is refused. A read-then-write would let
-- both pass the check and both spend. CHECK (balance_pence >= 0) is the
-- backstop if anyone ever writes a different update path.
--
-- credit_redemptions is an append-only ledger. balance_pence alone records
-- what is left but not where it went, and for anything holding money the
-- history is what makes a refund or a dispute answerable later.

CREATE TABLE credits (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    -- What the customer is emailed and types in at checkout. Opaque and
    -- random: it is a bearer instrument, so a guessable code is spendable money
    -- for whoever guesses it.
    code                  CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email                 VARCHAR(190) NOT NULL,
    currency              CHAR(3) NOT NULL DEFAULT 'GBP',
    -- What was paid. Never changes, so a partly-spent credit still shows its
    -- original face value on a receipt.
    amount_pence          INT UNSIGNED NOT NULL,
    -- What is left. Starts at 0 and is set to amount_pence only on activation.
    balance_pence         INT UNSIGNED NOT NULL DEFAULT 0,
    -- NULL means spendable against any event. Set means this event only, which
    -- is what a photographer selling credit at one meeting wants.
    event_id              INT UNSIGNED NULL,
    status                ENUM('pending','active','void') NOT NULL DEFAULT 'pending',
    stripe_checkout_id    VARCHAR(255) NULL,
    stripe_payment_intent VARCHAR(255) NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at          DATETIME NULL,
    UNIQUE KEY uq_credits_code (code),
    UNIQUE KEY uq_credits_checkout (stripe_checkout_id),
    KEY idx_credits_email (email),
    KEY idx_credits_status (status),
    CONSTRAINT fk_credits_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE SET NULL,
    -- Backstop. The spend path already refuses to overdraw; this makes an
    -- overdraft impossible even through a future code path that forgets to.
    CONSTRAINT chk_credits_balance CHECK (balance_pence >= 0),
    CONSTRAINT chk_credits_balance_max CHECK (balance_pence <= amount_pence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only ledger of every spend.
CREATE TABLE credit_redemptions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    credit_id    INT UNSIGNED NOT NULL,
    order_id     INT UNSIGNED NOT NULL,
    amount_pence INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- One credit contributes to one order at most once. Makes a retried
    -- checkout unable to spend the same credit into the same order twice.
    UNIQUE KEY uq_redemption (credit_id, order_id),
    KEY idx_redemption_order (order_id),
    CONSTRAINT fk_redemption_credit FOREIGN KEY (credit_id) REFERENCES credits (id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_redemption_order FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders record what credit paid for them.
--
-- total_pence keeps its existing meaning, the gross value of the order, so
-- every existing report and receipt stays correct. What Stripe is asked to
-- charge is total_pence - credit_applied_pence.
ALTER TABLE orders ADD COLUMN credit_applied_pence INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN credit_id INT UNSIGNED NULL;
