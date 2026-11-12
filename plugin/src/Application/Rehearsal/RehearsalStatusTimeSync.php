<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalStatus;

/**
 * StageArt 稽古管理ステータス自動同期 instruction: CONFIRMED/ACTIVE are not
 * advanced by a background scheduler - instead, whichever UseCase reads
 * or writes Rehearsal management data (GetRehearsalUseCase,
 * ListRehearsalsUseCase, UpdateRehearsalUseCase, CancelRehearsalUseCase,
 * and the five RehearsalAttendance UseCases that load the parent
 * Rehearsal) calls this helper first, so the DB status is brought up to
 * date with "now" at the moment someone actually looks at or touches
 * the Rehearsal, and the persisted (not merely displayed) status is
 * what every caller - this request and the next - sees from then on.
 *
 * Deliberately NOT inside RehearsalRepositoryInterface/its WordPress
 * implementation (a Repository is a pure persistence port, not a place
 * for Application-level business timing decisions) and NOT a Middleware/
 * global request hook (every one of this Module's own UseCases already
 * explicitly declares its own dependencies - see RehearsalModuleBootstrap.php -
 * an implicit cross-cutting hook would be the one architectural style
 * this codebase does not otherwise use). This mirrors
 * RehearsalReminderDispatcher's own precedent: a small, explicitly
 * injected, single-purpose Application-layer helper that the UseCases
 * which need it call directly.
 *
 * Only CONFIRMED->ACTIVE and ACTIVE->COMPLETED are synced here - exactly
 * the two transitions this instruction names. SCHEDULED->CONFIRMED,
 * any ->CANCELLED, and a direct SCHEDULED->ACTIVE are untouched; they
 * stay purely user-driven through the existing Confirm/Cancel UseCases
 * (Rehearsal::confirm()/cancel() are not called from here at all).
 *
 * Reuses Rehearsal::activate()/complete() (already-existing Domain Entity
 * methods backing the ActivateRehearsalUseCase/CompleteRehearsalUseCase
 * manual endpoints, which are untouched and left in place) rather than
 * inventing a new transition path - the Domain's own CONFIRMED-only/
 * ACTIVE-only guards are what make this safe to call from a read path:
 * calling sync() on a Rehearsal already in any other Status is a no-op.
 *
 * No new locking is introduced. A single Rehearsal transition + save is
 * exactly the same shape as the existing ActivateRehearsalUseCase/
 * CompleteRehearsalUseCase (no TransactionManagerInterface there either,
 * see their own docblocks) - this carries the identical, pre-existing
 * concurrent-request exposure those manual endpoints already had, not a
 * new one.
 */
final class RehearsalStatusTimeSync
{
    private RehearsalRepositoryInterface $rehearsals;

    public function __construct(RehearsalRepositoryInterface $rehearsals)
    {
        $this->rehearsals = $rehearsals;
    }

    public function sync(Rehearsal $rehearsal): Rehearsal
    {
        $now = new DateTimeImmutable();
        $changed = false;

        if ($rehearsal->status()->equals(RehearsalStatus::fromString(RehearsalStatus::CONFIRMED))
            && $rehearsal->startDateTime() !== null
            && $now >= $rehearsal->startDateTime()) {
            $rehearsal->activate();
            $changed = true;
        }

        if ($rehearsal->status()->equals(RehearsalStatus::fromString(RehearsalStatus::ACTIVE))
            && $rehearsal->endDateTime() !== null
            && $now >= $rehearsal->endDateTime()) {
            $rehearsal->complete();
            $changed = true;
        }

        if ($changed) {
            $this->rehearsals->save($rehearsal);
        }

        return $rehearsal;
    }

    /**
     * @param Rehearsal[] $rehearsals
     * @return Rehearsal[]
     */
    public function syncAll(array $rehearsals): array
    {
        return array_map(fn (Rehearsal $rehearsal): Rehearsal => $this->sync($rehearsal), $rehearsals);
    }
}
