<?php

declare(strict_types=1);

namespace StageArt\Application\JournalEntry;

use StageArt\Domain\JournalEntry\JournalEntryLine;

final class JournalEntryLineResult
{
    public string $id;
    public string $accountId;
    public string $debitCredit;
    public int $amount;
    public ?string $description;

    private function __construct(string $id, string $accountId, string $debitCredit, int $amount, ?string $description)
    {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->debitCredit = $debitCredit;
        $this->amount = $amount;
        $this->description = $description;
    }

    public static function fromDomain(JournalEntryLine $line): self
    {
        return new self(
            $line->id()->toString(),
            $line->accountId()->toString(),
            $line->debitCredit()->toString(),
            $line->amount(),
            $line->description()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->accountId,
            'debit_credit' => $this->debitCredit,
            'amount' => $this->amount,
            'description' => $this->description,
        ];
    }
}
