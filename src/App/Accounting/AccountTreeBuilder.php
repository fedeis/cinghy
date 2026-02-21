<?php

namespace App\Accounting;

class AccountTreeBuilder
{
    /**
     * Build a hierarchical tree from flat account balances
     * 
     * @param array $accounts Associative array of account => balance
     * @param string $prefix Filter accounts by prefix (e.g., "Expenses")
     * @return AccountNode Root node of the tree
     */
    public static function buildTree(array $accounts, string $prefix = ''): AccountNode
    {
        $root = new AccountNode('Root', '', 0);
        
        foreach ($accounts as $accountPath => $balance) {
            // Filter by prefix if specified
            if ($prefix && !str_starts_with($accountPath, $prefix)) {
                continue;
            }
            
            // Split account path by ':'
            $parts = explode(':', $accountPath);
            $currentNode = $root;
            $currentPath = '';
            
            foreach ($parts as $index => $part) {
                $currentPath = $currentPath ? $currentPath . ':' . $part : $part;
                
                // Find or create child node
                $childNode = null;
                foreach ($currentNode->children as $child) {
                    if ($child->name === $part) {
                        $childNode = $child;
                        break;
                    }
                }
                
                if (!$childNode) {
                    $childNode = new AccountNode($part, $currentPath, $index);
                    $currentNode->addChild($childNode);
                }
                
                // Add balance to leaf node
                if ($index === count($parts) - 1) {
                    $childNode->balance = $balance;
                }
                
                $currentNode = $childNode;
            }
        }
        
        // Rollup balances from children to parents
        self::rollupBalances($root);
        
        // Sort children by balance (largest first)
        $root->sortChildren();
        
        return $root;
    }
    
    /**
     * Rollup balances: parent balance = sum of all children
     */
    private static function rollupBalances(AccountNode $node): float
    {
        if (empty($node->children)) {
            return $node->balance;
        }
        
        $total = 0.0;
        foreach ($node->children as $child) {
            $total += self::rollupBalances($child);
        }
        
        $node->balance = $total;
        return $total;
    }
    
    /**
     * Get top-level accounts (first level children of root)
     */
    public static function getTopLevelAccounts(AccountNode $root): array
    {
        return $root->children[0]->children;
    }
    
    /**
     * Flatten tree to array for rendering
     */
    public static function flattenTree(AccountNode $node, int $maxLevel = PHP_INT_MAX): array
    {
        $result = [];
        
        if ($node->name !== 'Root') {
            $result[] = $node;
        }
        
        if ($node->level < $maxLevel) {
            foreach ($node->children as $child) {
                $result = array_merge($result, self::flattenTree($child, $maxLevel));
            }
        }
        
        return $result;
    }
}
