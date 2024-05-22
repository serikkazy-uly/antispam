<?php
declare(strict_types=1);
namespace App\Service;

class SpamChecker
{
    private $blocklist;

    public function __construct(string $blockListPath)
    {
        $this->blocklist = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public function isSpam(array $tokens, string $text): array
    {
        if ($this->containsBlockList($tokens, $text)) {
            return ['spam' => true, 'reason' => 'block_list'];
        }

        return ['spam' => false];
    }

    public function containsBlockList(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (in_array($token, $this->blocklist, true) || filter_var($token, FILTER_VALIDATE_EMAIL)) {
                return true;
            }

            return false;
        }
    }

}