<?php
declare(strict_types=1);
namespace App\Service;

class MessageNormalizer
{
    private array|false $stopWords;

    public function __construct(string $stopWordsPath)
    {
        $this->stopWords = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public function normalize(string $text): array
    {
        $tokens = preg_split('/[\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+/', $text);
//        error_log('Tokens after splitting: ' . json_encode($tokens));

        $tokens = array_diff($tokens, $this->stopWords);

        $tokens = array_map('mb_strtolower', $tokens);

        $tokens = array_filter($tokens, function ($token) {
            return !ctype_digit($token);
        });

        $tokens = array_filter($tokens, fn($token) => !empty($token));
//        dump($tokens);
        sort($tokens);
//        error_log('Tokens after sorting: ' . json_encode($tokens));
//        dump($tokens);
        return array_values($tokens);
//        return  dump(array_values($tokens));
//        return dump(implode('', $tokens));
    }
}
