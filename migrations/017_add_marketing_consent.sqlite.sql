-- Migration: contacts and marketing consent (SQLite variant).
--
-- See 017_add_marketing_consent.sql for why consent has to be modelled before
-- any campaign email is written, why transactional email is deliberately not
-- gated by this table, and what the two lawful bases mean.

CREATE TABLE IF NOT EXISTS contacts (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    email                TEXT NOT NULL,
    marketing_consent    INTEGER NOT NULL DEFAULT 0,
    consent_basis        TEXT NULL,             -- 'explicit' | 'soft_optin'
    consent_at           TEXT NULL,
    consent_ip           TEXT NULL,
    unsubscribed_at      TEXT NULL,
    unsubscribe_token    TEXT NOT NULL,
    created_at           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (email),
    UNIQUE (unsubscribe_token)
);

CREATE INDEX IF NOT EXISTS idx_contacts_sendable
    ON contacts (marketing_consent, unsubscribed_at);
