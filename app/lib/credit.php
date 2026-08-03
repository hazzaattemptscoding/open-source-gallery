<?php
/**
 * Advance credit: money a customer has paid in before the gallery exists.
 *
 * This is the only part of the application that stores spendable value, so it
 * is written more defensively than the rest. Three rules run through all of it:
 *
 *  1. Credit does not exist until Stripe says the money arrived. A row is
 *     created 'pending' with a zero balance and only activate_credit(), called
 *     from the webhook, gives it a balance. Nothing else may.
 *
 *  2. Spending is one conditional UPDATE, never a read followed by a write.
 *     Two checkouts submitted at the same moment both read the same balance; if
 *     the decision to spend is made in PHP, both spend it. Making the database
 *     decide, by refusing to update a row that no longer has enough, means
 *     exactly one wins.
 *
 *  3. Every movement is written to an append-only ledger and the audit log. A
 *     balance says what is left; only the ledger says where it went, and that
 *     is what answers a dispute or a refund months later.
 */

declare(strict_types=1);

require_once __DIR__ . '/audit.php';

/** Smallest credit worth selling, so a typo cannot create a 1p credit. */
const CREDIT_MIN_PENCE = 500;

/** Upper bound, as a guard against a fat-fingered or tampered amount. */
const CREDIT_MAX_PENCE = 50000;

/**
 * Generate a credit code.
 *
 * A credit code is a bearer instrument: whoever holds it can spend it. So it is
 * 8 random bytes rather than anything derived from the buyer, the amount or a
 * sequence, because a guessable code is money for whoever guesses it.
 *
 * Lower-case hex keeps it unambiguous to read aloud and to type on a phone.
 */
function generate_credit_code(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Create a pending credit at the start of a purchase.
 *
 * Deliberately unspendable: status 'pending' and balance 0. If the customer
 * abandons the Stripe page, this row simply sits there forever, worth nothing.
 *
 * @return array{id:int, code:string}|null
 */
function create_pending_credit(
    PDO $pdo,
    string $email,
    int $amountPence,
    string $currency,
    ?int $eventId = null
): ?array {
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    if ($amountPence < CREDIT_MIN_PENCE || $amountPence > CREDIT_MAX_PENCE) {
        return null;
    }

    $code = generate_credit_code();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO credits (code, email, currency, amount_pence, balance_pence, event_id, status)
             VALUES (?, ?, ?, ?, 0, ?, ?)'
        );
        $stmt->execute([$code, $email, $currency, $amountPence, $eventId, 'pending']);
    } catch (Throwable $e) {
        error_log('create_pending_credit failed: ' . $e->getMessage());
        return null;
    }

    return ['id' => (int) $pdo->lastInsertId(), 'code' => $code];
}

/** Record which Stripe session is paying for a credit, so the webhook can find it. */
function attach_credit_checkout(PDO $pdo, int $creditId, string $checkoutId): void
{
    try {
        $pdo->prepare('UPDATE credits SET stripe_checkout_id = ? WHERE id = ?')
            ->execute([$checkoutId, $creditId]);
    } catch (Throwable $e) {
        error_log('attach_credit_checkout failed: ' . $e->getMessage());
    }
}

/**
 * Turn a paid-for credit into spendable money. Called only from the Stripe
 * webhook.
 *
 * The UPDATE is guarded on status = 'pending', which makes it idempotent:
 * Stripe retries webhooks, and a second delivery finds the row already active
 * and changes nothing. Without that guard a retry would reset the balance to
 * full face value after the customer had already spent some, which is the
 * expensive version of this bug.
 *
 * @return bool True when this call activated it.
 */
function activate_credit(PDO $pdo, string $checkoutId, ?string $paymentIntentId = null): bool
{
    try {
        $stmt = $pdo->prepare(
            "UPDATE credits
                SET status = 'active',
                    balance_pence = amount_pence,
                    stripe_payment_intent = ?,
                    activated_at = CURRENT_TIMESTAMP
              WHERE stripe_checkout_id = ?
                AND status = 'pending'"
        );
        $stmt->execute([$paymentIntentId, $checkoutId]);

        if ($stmt->rowCount() === 0) {
            return false;
        }
    } catch (Throwable $e) {
        error_log('activate_credit failed: ' . $e->getMessage());
        return false;
    }

    audit_log($pdo, 'system', 'credit_activated', 'credit', null, [
        'checkout_id' => $checkoutId,
    ]);

    return true;
}

/**
 * Look up a spendable credit by code, in a given currency.
 *
 * Returns null for anything not currently spendable, without saying why: a code
 * is a bearer token, and distinguishing "wrong code" from "already spent" for
 * an anonymous caller helps someone probing for valid codes more than it helps
 * a customer.
 *
 * Why currency is a required argument
 * -----------------------------------
 * A balance is a number of minor units and nothing else. Applying 2000 units of
 * credit sold in one currency against an order priced in another is not a
 * conversion, it is a one-for-one swap at whatever rate the two currencies
 * happen to differ by, and the customer gains or loses real money depending on
 * which direction the operator changed it.
 *
 * The mismatch is refused rather than converted. Converting needs an exchange
 * rate, a rate needs a source and a date, and none of that belongs in a
 * self-hosted gallery. Refusing is a support conversation; converting silently
 * is a wrong number in someone's books.
 *
 * Making the argument required rather than optional is deliberate: an optional
 * currency check is a check that is off by default, and this one existing but
 * being unused is exactly the bug it fixes.
 *
 * @param string $currency ISO 4217 code the spend will be denominated in.
 * @return array<string,mixed>|null
 */
function find_spendable_credit(PDO $pdo, string $code, string $currency): ?array
{
    $code = strtolower(trim($code));
    if (preg_match('/^[0-9a-f]{16}$/', $code) !== 1) {
        return null;
    }

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        return null;
    }

    try {
        // Compared in SQL rather than in PHP so there is no path that fetches
        // the row and then forgets to look at the currency.
        $stmt = $pdo->prepare(
            "SELECT * FROM credits
              WHERE code = ?
                AND UPPER(currency) = ?
                AND status = 'active'
                AND balance_pence > 0
              LIMIT 1"
        );
        $stmt->execute([$code, $currency]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('find_spendable_credit failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Is this credit usable against this event?
 *
 * A credit with no event is usable anywhere. One tied to an event is usable
 * only there, which is what a photographer selling credit at a single meeting
 * expects.
 */
function credit_applies_to_event(array $credit, ?int $eventId): bool
{
    if ($credit['event_id'] === null) {
        return true;
    }
    return $eventId !== null && (int) $credit['event_id'] === $eventId;
}

/**
 * Can this credit pay for this cart?
 *
 * A credit with no event is spendable on anything, so this is only a question
 * for event-scoped credit. In that case every line must belong to that event:
 * allowing a mixed cart would let a credit bought at one meeting pay for
 * another meeting's photos as long as one qualifying line was present.
 *
 * Resolves each line to its event rather than trusting anything the client
 * sent, since the cart cookie holds ids the customer could edit.
 *
 * @param list<array<string,mixed>> $lines Priced cart lines.
 */
function credit_cart_matches_event(PDO $pdo, array $lines, array $credit): bool
{
    if ($credit['event_id'] === null) {
        return true;
    }

    $creditEventId = (int) $credit['event_id'];

    foreach ($lines as $line) {
        $lineEventId = null;

        try {
            if ($line['type'] === 'photo') {
                $stmt = $pdo->prepare('SELECT event_id FROM photos WHERE id = ?');
                $stmt->execute([(int) $line['id']]);
                $lineEventId = $stmt->fetchColumn();
            } elseif ($line['type'] === 'session_bundle') {
                $stmt = $pdo->prepare('SELECT event_id FROM sessions WHERE id = ?');
                $stmt->execute([(int) $line['id']]);
                $lineEventId = $stmt->fetchColumn();
            } elseif ($line['type'] === 'event_bundle') {
                $lineEventId = (int) $line['id'];
            }
        } catch (Throwable $e) {
            error_log('credit_cart_matches_event lookup failed: ' . $e->getMessage());
            return false;
        }

        // Unresolvable line: refuse rather than assume it qualifies.
        if ($lineEventId === false || $lineEventId === null) {
            return false;
        }

        if ((int) $lineEventId !== $creditEventId) {
            return false;
        }
    }

    return true;
}

/**
 * Spend up to $requestedPence from a credit, atomically.
 *
 * This is the function the whole feature turns on.
 *
 * The amount to take is decided in SQL, not in PHP: the UPDATE subtracts the
 * smaller of the request and the current balance, and only runs at all while
 * the balance is still positive. Two concurrent checkouts therefore cannot both
 * take the same money. Whichever UPDATE the database applies second sees the
 * balance the first one left behind.
 *
 * Returns the amount actually taken, which may be less than requested when the
 * credit is nearly exhausted, or zero when another request got there first.
 *
 * The ledger row is written after the balance moves, keyed uniquely on
 * (credit_id, order_id), so a retried checkout for the same order cannot
 * double-count even if it somehow reached this twice.
 */
function spend_credit(PDO $pdo, int $creditId, int $orderId, int $requestedPence, string $currency): int
{
    if ($requestedPence <= 0) {
        return 0;
    }

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        return 0;
    }

    try {
        // LEAST() is the portable way to say "take what is there, up to what was
        // asked for" without reading the balance into PHP first. SQLite spells
        // it MIN(); both are handled by db_least_function() below.
        $least = db_least_function($pdo);

        /*
         * The CAST is not decoration, and neither is the PARAM_INT binding.
         *
         * PDO binds parameters as strings unless told otherwise. In SQLite an
         * INTEGER always sorts before TEXT, so MIN(2000, '500') returns 2000,
         * not 500. Without both of these the decrement subtracts the entire
         * balance no matter how little was asked for: a customer buying GBP 20
         * of credit and spending GBP 5 would silently lose the other GBP 15.
         *
         * Belt and braces on purpose. The binding is the real fix; the CAST
         * means a future caller that binds differently still cannot cause it.
         */
        $stmt = $pdo->prepare(
            "UPDATE credits
                SET balance_pence = balance_pence - {$least}(balance_pence, CAST(:amount AS INTEGER))
              WHERE id = :id
                AND status = 'active'
                AND balance_pence > 0
                AND UPPER(currency) = :currency"
        );
        $stmt->bindValue(':amount', $requestedPence, PDO::PARAM_INT);
        $stmt->bindValue(':id', $creditId, PDO::PARAM_INT);
        // The currency belongs in the WHERE clause, not in a PHP check before
        // it. The caller found this credit with one lookup and spends it with
        // another; putting the condition on the write means no state can change
        // in between and no future caller can skip it.
        $stmt->bindValue(':currency', $currency);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            // Either it was exhausted, voided, or another request took it
            // between the caller's read and this write. All mean "no money".
            return 0;
        }

        // Work out what was actually taken by re-reading. Safe because the
        // decrement has already happened atomically; this is reporting the
        // result, not deciding it.
        $check = $pdo->prepare('SELECT amount_pence, balance_pence FROM credits WHERE id = ?');
        $check->execute([$creditId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return 0;
        }

        $spentTotal = (int) $row['amount_pence'] - (int) $row['balance_pence'];

        $ledger = $pdo->prepare(
            'INSERT INTO credit_redemptions (credit_id, order_id, amount_pence) VALUES (?, ?, ?)'
        );

        // How much this particular call took: the total spent so far minus
        // whatever earlier orders already recorded against this credit.
        $prior = $pdo->prepare(
            'SELECT COALESCE(SUM(amount_pence), 0) FROM credit_redemptions WHERE credit_id = ?'
        );
        $prior->execute([$creditId]);
        $taken = $spentTotal - (int) $prior->fetchColumn();

        if ($taken <= 0) {
            return 0;
        }

        $ledger->execute([$creditId, $orderId, $taken]);

        audit_log($pdo, 'public', 'credit_spent', 'credit', $creditId, [
            'order_id' => $orderId,
            'amount_pence' => $taken,
        ]);

        return $taken;
    } catch (Throwable $e) {
        error_log('spend_credit failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Give credit back after a checkout failed before the customer could pay.
 *
 * Why this has to exist
 * --------------------
 * Credit is spent before the Stripe session is created, because the spend has
 * to be atomic and settled before anything decides what to charge. If creating
 * that session then fails, the balance has already gone down and the customer
 * has nothing: their money has evaporated into an order they never completed.
 * That is the worst failure this feature can have, and it is entirely
 * recoverable, so it is recovered.
 *
 * Runs in a transaction. The two halves, restoring the balance and removing the
 * ledger row, must both happen or neither: restoring without removing lets the
 * same amount be released twice, and removing without restoring loses the money
 * exactly as before.
 *
 * Refuses to act on a paid order. If payment did land, the credit was correctly
 * spent and clawing it back would be taking money for goods already delivered.
 *
 * @return bool True when credit was returned.
 */
function release_credit(PDO $pdo, int $creditId, int $orderId): bool
{
    $ownTransaction = !$pdo->inTransaction();

    try {
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        $paid = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $paid->execute([$orderId]);
        if ((string) $paid->fetchColumn() === 'paid') {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT amount_pence FROM credit_redemptions WHERE credit_id = ? AND order_id = ?'
        );
        $stmt->execute([$creditId, $orderId]);
        $amount = $stmt->fetchColumn();

        if ($amount === false || (int) $amount <= 0) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return false;
        }
        $amount = (int) $amount;

        // Guarded so a balance can never be restored past the credit's face
        // value, whatever else has happened to it in the meantime.
        $restore = $pdo->prepare(
            'UPDATE credits
                SET balance_pence = balance_pence + :amount
              WHERE id = :id
                AND balance_pence + :amount2 <= amount_pence'
        );
        $restore->bindValue(':amount', $amount, PDO::PARAM_INT);
        $restore->bindValue(':amount2', $amount, PDO::PARAM_INT);
        $restore->bindValue(':id', $creditId, PDO::PARAM_INT);
        $restore->execute();

        if ($restore->rowCount() === 0) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        $pdo->prepare('DELETE FROM credit_redemptions WHERE credit_id = ? AND order_id = ?')
            ->execute([$creditId, $orderId]);

        $pdo->prepare('UPDATE orders SET credit_applied_pence = 0, credit_id = NULL WHERE id = ?')
            ->execute([$orderId]);

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('release_credit failed: ' . $e->getMessage());
        return false;
    }

    audit_log($pdo, 'system', 'credit_released', 'credit', $creditId, [
        'order_id' => $orderId,
        'amount_pence' => $amount,
    ]);

    return true;
}

/**
 * The SQL function name for "smaller of two values" on this driver.
 *
 * MySQL calls it LEAST, SQLite calls it MIN. Kept here rather than inline so
 * the one place that matters, the atomic decrement, reads clearly.
 */
function db_least_function(PDO $pdo): string
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    return $driver === 'sqlite' ? 'MIN' : 'LEAST';
}

/** Everything spent against one credit, newest first, for admin and support. */
function fetch_credit_redemptions(PDO $pdo, int $creditId): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT cr.*, o.public_token, o.status AS order_status
               FROM credit_redemptions cr
               JOIN orders o ON o.id = cr.order_id
              WHERE cr.credit_id = ?
              ORDER BY cr.created_at DESC'
        );
        $stmt->execute([$creditId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('fetch_credit_redemptions failed: ' . $e->getMessage());
        return [];
    }
}
