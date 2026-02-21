<?php

namespace App\Accounting;

class Posting
{
    public string $account;
    public float $amount;
    public string $currency; // EUR, USD, etc.

    public function __construct(string $account, float $amount, string $currency)
    {
        $this->account = $account;
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public static function fromArray(array $data): self
    {
        return new self($data['account'], $data['amount'], $data['currency']);
    }

    public function toArray(): array
    {
        return [
            'account' => $this->account,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
