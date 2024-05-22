<?php
declare(strict_types=1);
namespace App\Service;

class SpamChecker
{
    private $blocklist;
    private $redis;

    public function __construct(string $blockListPath, \Redis $redis)
    {
        $this->blocklist = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->redis = $redis;
    }

    public function isSpam(array $tokens, string $text, bool $checkRate): array
    {
        if ($this->containsBlockList($tokens, $text)) {
            return ['spam' => true, 'reason' => 'block_list'];
        }
        if ($this->containsMixedWords($tokens)) {
            return ['spam' => true, 'reason' => 'mixed_words'];
        }

        if ($this->isDuplicate($tokens)) {
            return ['spam' => true, 'reason' => 'duplicate'];
        }

        if ($checkRate && $this->checkRate($text)) {
            return ['spam' => true, 'reason' => 'check_rate'];
        }

        return ['spam' => false];
    }

    private function containsBlockList(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (in_array($token, $this->blocklist, true) || filter_var($token, FILTER_VALIDATE_EMAIL)) {
                return true;
            }
        }

        return false;
    }

    private function containsMixedWords(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (preg_match('/[а-яА-Я]/u', $token) && preg_match('/[a-zA-Z]/', $token)) {
                return true;
            }
        }

        return false;
    }

    private function isDuplicate(array $tokens): bool
    {
        $normalized       = implode(' ', $tokens);
        $redisKey         = 'spam_checker:previous_messages';
        $previousMessages = $this->redis->lrange($redisKey, 0, -1);

        foreach ($previousMessages as $message) {
            similar_text($normalized, $message, $percent);
            if ($percent >= 60) {
                return true;
            }
        }

        $this->redis->lpush($redisKey, $normalized);
        $this->redis->ltrim($redisKey, 0, 4);

        return false;

    }

    private function checkRate(string $text): bool
    {
        $redisKey    = 'spam_checker:message_timestamps';
        $currentTime = microtime(true);
        $this->redis->lpush($redisKey, $currentTime);

        $timestamps = $this->redis->lrange($redisKey, 0, 1);

        if (count($timestamps) < 2) {
            return false;
        }

        $timeDifference = $timestamps[0] - $timestamps[1];

        $this->redis->ltrim($redisKey, 0, 9);

        return $timeDifference < 2;
    }

}