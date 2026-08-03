-- Migration: contacts and marketing consent.
--
-- Why this has to exist before any campaign email is written
-- ---------------------------------------------------------
-- The planned nudge funnel (gallery live, early bird ending, abandoned cart,
-- gallery expiring) is marketing email, not transactional email, and in the UK
-- that is governed by PECR as well as UK GDPR. Sending it requires a lawful
-- basis recorded per recipient, and every message must carry a working
-- unsubscribe. Until now the app had no concept of consent at all: it knew a
-- buyer's email from the orders table and nothing about whether it was allowed
-- to contact them again.
--
-- Building the funnel first and bolting consent on afterwards would mean the
-- first send is the one that breaks the law, so this lands first.
--
-- Transactional versus marketing
-- ------------------------------
-- Receipts, download links and refund notices are transactional: they complete
-- a transaction the customer asked for, need no consent, and must keep sending
-- regardless of what is recorded here. Nothing in this migration gates them.
-- Only the campaign emails consult this table.
--
-- The two lawful bases this models
-- --------------------------------
--   'explicit'  the person actively ticked a box. Required for anyone who has
--               not bought anything, such as a visitor who left an email to be
--               told when a gallery goes live.
--   'soft_optin' PECR regulation 22(3): the person bought something, the
--               marketing is about similar products, and every message gives
--               them a way to opt out. This is why a buyer can be sent "your
--               gallery is expiring" without having ticked anything.
--
-- Storing which one applies matters: if a complaint arrives, "we had consent"
-- is not an answer, "here is the basis, the timestamp and the IP" is.
--
-- One row per email address, not per order. A parent who buys at four rounds is
-- one person with one consent state and one unsubscribe link, and unsubscribing
-- from one of those emails has to stop all of them.

CREATE TABLE contacts (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email                VARCHAR(190) NOT NULL,
    -- 1 only while a live lawful basis exists. Set back to 0 on unsubscribe so
    -- a single boolean answers "may we send to this person", and the basis and
    -- timestamps below explain why.
    marketing_consent    TINYINT(1) NOT NULL DEFAULT 0,
    consent_basis        VARCHAR(16) NULL,      -- 'explicit' | 'soft_optin'
    consent_at           DATETIME NULL,
    -- Evidence, kept because the burden of proof is on the sender. This is the
    -- IP that performed the opt-in, not a tracking identifier.
    consent_ip           VARCHAR(45) NULL,
    unsubscribed_at      DATETIME NULL,
    -- Opaque, unguessable, and stable for the life of the contact: it goes in
    -- the footer of every campaign email, so it must work without a login and
    -- must not be derivable from the email address.
    unsubscribe_token    CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contacts_email (email),
    UNIQUE KEY uq_contacts_unsub (unsubscribe_token),
    -- The campaign selector's only question: who may be emailed right now.
    KEY idx_contacts_sendable (marketing_consent, unsubscribed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
