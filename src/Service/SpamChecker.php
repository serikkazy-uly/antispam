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

    public function isSpam(array $tokens, string $normalized, bool $checkRate): array
    {
        if ($this->containsBlockList($tokens)) {
            return ['status' => 'ok', 'spam' => true, 'reason' => 'block_list'];
        }
        if ($this->containsMixedWords($tokens)) {
            return ['status' => 'ok', 'spam' => true, 'reason' => 'mixed_words'];
        }

        if ($this->isDuplicate($normalized)) {
            return ['status' => 'ok', 'spam' => true, 'reason' => 'duplicate'];
        }

        if ($checkRate && $this->checkRate()) {
            return ['status' => 'ok', 'spam' => true, 'reason' => 'check_rate'];
        }

        return ['status' => 'ok', 'spam' => false];
    }

    private function containsBlockList(array $tokens): bool
    {
//        dump($tokens);
//        dump($this->blocklist);
        foreach ($tokens as $token) {
            if (in_array($token, $this->blocklist, true) || filter_var($token, FILTER_VALIDATE_EMAIL)) {
//                var_dump($this->blocklist);
                return true;
            }
        }

        return false;
    }

    private function containsMixedWords(array $tokens): bool
    {
//        dump($tokens);
        foreach ($tokens as $token) {
            $hasCyrillic = preg_match('/[а-яА-Я]/u', $token);
            $hasLatin = preg_match('/[a-zA-Z]/', $token);
//            dump(['cyrillic' => $hasCyrillic, 'latin' => $hasLatin]);

            if ($hasCyrillic && $hasLatin) {
                return true;
            }
        }
        return false;
    }

//    private function isDuplicate(array $tokens): bool
//    {
////        dump($tokens);
//        $normalized = implode(' ', $tokens);
//        $key = 'spam_checker_previous_messages';
////        dump($key);
//        $previousMessages = $this->cache->get($key, function(ItemInterface $item) {
//            $item->expiresAfter(2);
////            dump($item);
//            return [];
//        });
//
//        foreach ($previousMessages as $message) {
//            similar_text($normalized, $message, $percent);
//            if ($percent >= 60) {
////            dump($percent);
//                return true;
//            }
//        }
//
//        $previousMessages[] = $normalized;
////        dump($previousMessages);
//        if (count($previousMessages) > 10) {
//            array_shift($previousMessages);
//        }
//        $this->cache->save($this->cache->getItem($key)->set($previousMessages)->expiresAfter(2));
//        //dump($this->cache);
//        return false;
//    }

    private function isDuplicate(string $normalized): bool
    {
        $key = 'spam_checker_previous_messages';
        $previousMessages = $this->cache->get($key, function(ItemInterface $item) {
            $item->expiresAfter(2);
            return [];
        });

        foreach ($previousMessages as $message) {
            similar_text($normalized, $message, $percent);
            if ($percent >= 60) {
                return true;
            }
        }

        $previousMessages[] = $normalized;
        if (count($previousMessages) > 10) {
            array_shift($previousMessages);
        }
        $this->cache->save($this->cache->getItem($key)->set($previousMessages)->expiresAfter(2));
        return false;
    }

    private function checkRate(): bool
    {
        $key = 'spam_checker_message_timestamps';
        $currentTime = microtime(true);

        $timestamps = $this->cache->get($key, function(ItemInterface $item) use ($currentTime) {
            $item->expiresAfter(2);
            return [$currentTime];
        });

        $timestamps[] = $currentTime;

        if (count($timestamps) < 2) {
            $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter(2));
            return false;
        }

        $timeDifference = $timestamps[count($timestamps) - 1] - $timestamps[count($timestamps) - 2];

        if (count($timestamps) > 10) {
            array_shift($timestamps);
        }
        $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter(2));

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

    public function clearCache(): void
    {
        $this->cache->deleteItem('spam_checker_previous_messages');
        $this->cache->deleteItem('spam_checker_message_timestamps');
    }

}
