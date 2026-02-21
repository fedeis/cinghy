<?php

namespace App\Accounting;

class Transaction
{
    public string $date;
    public string $payee;
    public string $description; // Memo / Narrative
    public string $status; // *, !, or empty
    public array $postings = []; // Array of Posting objects

    public function __construct(string $date, string $payee, string $description = '', string $status = '')
    {
        $this->date = $date;
        $this->payee = $payee;
        $this->description = $description;
        $this->status = $status;
    }

    public function addPosting(Posting $posting): void
    {
        $this->postings[] = $posting;
    }

    public function getPostings(): array
    {
        return $this->postings;
    }

    public function setPostings(array $postings): void
    {
        $this->postings = $postings;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'payee' => $this->payee,
            'description' => $this->description,
            'status' => $this->status,
            'postings' => array_map(fn($p) => $p->toArray(), $this->postings),
        ];
    }
}
