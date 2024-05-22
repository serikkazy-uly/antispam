<?php
declare(strict_types=1);

namespace App\Service;

class MessageNormalizer
{
    private $stopWords;

    public function __construct(string $stopWordsPath)
    {
        $this->stopWords = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }


    public function normalize(string $text): array
    {
        $tokens = preg_split('/[\.\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+/', $text);
        $tokens = array_map('mb_strtolower', $tokens);
        $tokens = array_diff($tokens, $this->stopWords);
        $tokens = array_filter($tokens, function ($token) {
            return !ctype_digit($token);
        });
        sort($tokens);

        return $tokens;
    }
}