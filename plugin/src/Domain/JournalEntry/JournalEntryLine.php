<?php

declare(strict_types=1);

namespace StageArt\Domain\JournalEntry;

use InvalidArgumentException;
use StageArt\Domain\Account\AccountId;

final class JournalEntryLine
{
    private JournalEntryLineId $id;
    private AccountId $accountId;
    private DebitCredit $debitCredit;
    private int $amount;
    private ?string $description;

    private function __construct(
        JournalEntryLineId $id,
        AccountId $accountId,
        DebitCredit $debitCredit,
        int $amount,
        ?string $description
    ) {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->debitCredit = $debitCredit;
        $this->amount = $amount;
        $this->description = $description;
    }

    public static function create(
        AccountId $accountId,
        DebitCredit $debitCredit,
        int $amount,
        ?string $description = null
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('JournalEntryLine amount must be a positive integer.');
        }

        return new self(JournalEntryLineId::generate(), $accountId, $debitCredit, $amount, $description);
    }

    public static function reconstitute(
        JournalEntryLineId $id,
        AccountId $accountId,
        DebitCredit $debitCredit,
        int $amount,
        ?string $description
    ): self {
        return new self($id, $accountId, $debitCredit, $amount, $description);
    }

    public function id(): JournalEntryLineId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function debitCredit(): DebitCredit
    {
        return $this->debitCredit;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
