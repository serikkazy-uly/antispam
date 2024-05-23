<?php
declare(strict_types=1);
namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SpamChecker
{
    private $blocklist;
    private CacheInterface $cache;

    public function __construct(string $blockListPath, CacheInterface $cache)
    {
        $this->blocklist = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->cache = $cache;
    }

    public function isSpam(array $tokens, string $text, bool $checkRate): array
    {
        if ($this->containsBlockList($tokens)) {
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
        $normalized = implode(' ', $tokens);
        $key = 'spam_checker_previous_messages';

        $previousMessages = $this->cache->get($key, function(ItemInterface $item) {
            $item->expiresAfter(3600);
            return [];
        });

        foreach ($previousMessages as $message) {
            similar_text($normalized, $message, $percent);
            if ($percent >= 60) {
                return true;
            }
        }

        $previousMessages[] = $normalized;
        $this->cache->save($this->cache->getItem($key)->set($previousMessages));

        return false;
    }

    private function checkRate(): bool
    {
        $key = 'spam_checker_message_timestamps';
        $currentTime = microtime(true);

        $timestamps = $this->cache->get($key, function(ItemInterface $item) use ($currentTime) {
            $item->expiresAfter(3600);
            return [$currentTime];
        });

        $timestamps[] = $currentTime;

        if (count($timestamps) < 2) {
            $this->cache->save($this->cache->getItem($key)->set($timestamps));

            return false;
        }

        $timeDifference = $timestamps[count($timestamps) - 1] - $timestamps[count($timestamps) - 2];

        if (count($timestamps) > 10) {
            array_shift($timestamps);
        }
        $this->cache->save($this->cache->getItem($key)->set($timestamps));

        return $timeDifference < 2;
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function getCacheContent(string $key): array
    {
        return $this->cache->get($key, function() {
            return [];
        });
    }


}
