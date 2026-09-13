<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

use StageArt\Domain\Ticket\Ticket;

final class TicketResult
{
    public string $id;
    public string $productionId;
    public string $name;
    public int $price;
    public ?string $remarks;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $productionId,
        string $name,
        int $price,
        ?string $remarks,
        string $status,
        string $createdAt,
        string $updatedAt
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

    public static function fromDomain(Ticket $ticket): self
    {
        return new self(
            $ticket->id()->toString(),
            $ticket->productionId()->toString(),
            $ticket->name(),
            $ticket->price(),
            $ticket->remarks(),
            $ticket->status()->toString(),
            $ticket->createdAt()->format(DATE_ATOM),
            $ticket->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'name' => $this->name,
            'price' => $this->price,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
