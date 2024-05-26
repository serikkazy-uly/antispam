<?php
declare(strict_types=1);
namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SpamChecker
{
    private array $stopWords;
    private array $blockList;
    private CacheInterface $cache;

    public function __construct(string $stopWordsPath, string $blockListPath, CacheInterface $cache)
    {
        $this->stopWords = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->blockList = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->cache = $cache;
    }

    public function isSpam(string $text, bool $checkRate): array
    {
        $normalizedText = $this->normalize($text);
        $normalizedTextString = implode(' ', $normalizedText);

        if ($this->containsBlockList($normalizedText)) {
            return [
                'status' => 'ok',
                'spam' => true,
                'reason' => 'block_list',
                'normalized_text' => $normalizedTextString
            ];
        }

        if ($this->containsMixedWords($normalizedText)) {
            return [
                'status' => 'ok',
                'spam' => true,
                'reason' => 'mixed_words',
                'normalized_text' => $normalizedTextString
            ];
        }

        if ($this->isDuplicate($normalizedTextString)) {
            return [
                'status' => 'ok',
                'spam' => true,
                'reason' => 'duplicate',
                'normalized_text' => $normalizedTextString
            ];
        }

        if ($checkRate && $this->checkRate()) {
            return [
                'status' => 'ok',
                'spam' => true,
                'reason' => 'check_rate'
//                'normalized_text' => $normalizedTextString
            ];
        }

        return [
            'status' => 'ok',
            'spam' => false,
            'reason' => '',
            'normalized_text' => $normalizedTextString
        ];
    }

    private function normalize(string $text): array
    {
        $tokens = preg_split('/[\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+/', $text);
        $tokens = array_map('mb_strtolower', $tokens);
        $tokens = array_diff($tokens, $this->stopWords);
        $tokens = array_filter($tokens, fn($token) => !ctype_digit($token) && !empty($token));
        sort($tokens);
        return array_values($tokens);
    }

    private function containsBlockList(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (in_array($token, $this->blockList) || filter_var($token, FILTER_VALIDATE_EMAIL)) {
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

    private function isDuplicate(string $normalizedText): bool
    {
        $key = 'spam_checker_previous_messages';
        $previousMessages = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(3600);
            return [];
        });

        foreach ($previousMessages as $message) {
            similar_text($normalizedText, $message, $percent);
                if ($percent >= 60) {
                    return true;
            }
        }

        $previousMessages[] = $normalizedText;
        if (count($previousMessages) > 10) {
            array_shift($previousMessages);
        }
        $this->cache->save($this->cache->getItem($key)->set($previousMessages)->expiresAfter(3600));
        return false;
    }

    private function checkRate(): bool
    {
        $key = 'spam_checker_message_timestamps';
        $currentTime = microtime(true);

        $timestamps = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(2);
//            return [$currentTime];
            return [];
        });

        $timestamps[] = $currentTime;

        $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter(2));
        if (count($timestamps) < 2) {
            return false;
        }

        $timeDifference = $timestamps[count($timestamps) - 1] - $timestamps[count($timestamps) - 2];

        if (count($timestamps) > 10) {
            array_shift($timestamps);
        }
        $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter(2));

        return $timeDifference < 2;
    }

    public function clearCache(): void
    {
        $this->cache->deleteItem('spam_checker_previous_messages');
        $this->cache->deleteItem('spam_checker_message_timestamps');
    }
}
