<?php
//declare(strict_types=1);
//namespace App\Service;
//
//class MessageNormalizer
//{
//    private array|false $stopWords;
//
//    public function __construct(string $stopWordsPath)
//    {
//        $this->stopWords = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//    }
//
//    public function normalize(string $text): array
//    {
//        $tokens = preg_split('/[\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+/', $text);
////        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|[^\s.,!?[\]()<>:;\-\n\'"\r\/\*\|]+/', $text, $matches);
////        $tokens = $matches[0];
//        $tokens = array_diff($tokens, $this->stopWords);
//        $tokens = array_map('mb_strtolower', $tokens);
//        $filteredTokens = array_filter($tokens, function ($token) {
//            return !ctype_digit($token) && !empty($token);
////            return !ctype_digit($token);
//
//        });
////        $filteredTokens = array_filter($filteredTokens, fn($token) => !empty($token));
//        sort($filteredTokens);
//        return array_values($filteredTokens);
////        return  dump(array_values($tokens));
//    }
//}
