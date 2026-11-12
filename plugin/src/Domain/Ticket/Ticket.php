<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Production\ProductionId;

/**
 * Phase 3 Ticket/Reservation基盤 instruction §5/§6: Ticket is a flat
 * Production-owned sales condition (name + price + remarks only) -
 * deliberately NOT the two-axis Matrix structure some older Blueprint
 * chapters describe (see the Phase 3 audit's judgment-pending item ②,
 * resolved in favor of the flat structure by this instruction's explicit
 * §3/§5/§49 "二軸MatrixはPhase 3では実装しない"). "前売"/"当日"/"学生"
 * etc. are never special hardcoded types - they are just Ticket names a
 * manager types in (§5).
 *
 * Ticket carries no capacity of its own (§6 - capacity belongs entirely
 * to Performance) and no publication/sales-window fields of its own
 * (§7/§8/§36 - those are Production-common settings, see Production::
 * ticketPublicationAt()/ticketSalesStartAt()/ticketSalesEndRule()).
 */
final class Ticket
{
    private TicketId $id;
    private ProductionId $productionId;
    private string $name;
    private int $price;
    private ?string $remarks;
    private TicketStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        TicketId $id,
        ProductionId $productionId,
        string $name,
        int $price,
        ?string $remarks,
        TicketStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->productionId = $productionId;
        $this->name = $name;
        $this->price = $price;
        $this->remarks = $remarks;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(ProductionId $productionId, string $name, int $price, ?string $remarks): self
    {
        $now = new DateTimeImmutable();

        return new self(
            TicketId::generate(),
            $productionId,
            self::validateName($name),
            self::validatePrice($price),
            self::normalizeNullableString($remarks),
            TicketStatus::active(),
            $now,
            $now
        );
    }

    public static function reconstitute(
        TicketId $id,
        ProductionId $productionId,
        string $name,
        int $price,
        ?string $remarks,
        TicketStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $productionId, $name, $price, $remarks, $status, $createdAt, $updatedAt);
    }

    /**
     * §44: no explicit Blueprint guidance was found on whether a
     * published Ticket's price may still be edited once Reservations
     * exist against it - the safe default (no destructive versioning
     * machinery per §44's own instruction) is to allow the edit, because
     * Price Snapshot (Reservation::priceSnapshot) already guarantees an
     * existing Reservation's price is never affected by a later Ticket
     * price change (see Reservation.md's own "Ticket Price and
     * Reservation" section, already the confirmed mechanism this
     * codebase relies on for Rehearsal/Production edits too).
     */
    public function updateBasicInfo(string $name, int $price, ?string $remarks): void
    {
        if ($this->status->equals(TicketStatus::fromString(TicketStatus::ARCHIVED))) {
            throw new InvalidArgumentException('An ARCHIVED Ticket cannot be edited.');
        }

        $this->name = self::validateName($name);
        $this->price = self::validatePrice($price);
        $this->remarks = self::normalizeNullableString($remarks);
        $this->touch();
    }

    /**
     * §44's "delete" concern, addressed via soft-delete: an ARCHIVED
     * Ticket disappears from selectable options for new Reservations but
     * is never physically removed, keeping every existing Reservation's
     * Price Snapshot and Ticket reference intact.
     */
    public function archive(): void
    {
        if ($this->status->equals(TicketStatus::fromString(TicketStatus::ARCHIVED))) {
            throw new InvalidArgumentException('Ticket is already ARCHIVED.');
        }

        $this->status = TicketStatus::fromString(TicketStatus::ARCHIVED);
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->status->equals(TicketStatus::fromString(TicketStatus::ACTIVE));
    }

    private static function validateName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Ticket name must not be empty.');
        }

        return $trimmed;
    }

    /**
     * §4/指示書確定事項①: price > 0 のみ許可する。0円Ticket・無料Ticket・
     * 招待/関係者/モニターを0円で登録する方式はいずれも今回のTicket価格
     * 管理では扱わない。UIだけでなくDomainで必ず検証する。
     */
    private static function validatePrice(int $price): int
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('Ticket price must be a positive integer greater than zero.');
        }

        return $price;
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): TicketId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
