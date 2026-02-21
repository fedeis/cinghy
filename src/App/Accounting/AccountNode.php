<?php

namespace App\Accounting;

class AccountNode
{
    public string $name;
    public string $fullPath;
    public float $balance;
    public array $children;
    public int $level;
    
    public function __construct(string $name, string $fullPath, int $level = 0)
    {
        $this->name = $name;
        $this->fullPath = $fullPath;
        $this->balance = 0.0;
        $this->children = [];
        $this->level = $level;
    }
    
    public function addChild(AccountNode $child): void
    {
        $this->children[] = $child;
    }
    
    public function hasChildren(): bool
    {
        return !empty($this->children);
    }
    
    public function sortChildren(): void
    {
        usort($this->children, fn($a, $b) => abs($b->balance) <=> abs($a->balance));
        foreach ($this->children as $child) {
            $child->sortChildren();
        }
    }
}
