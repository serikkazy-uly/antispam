<?php
declare(strict_types=1);
namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/*
 * Класс проверяет текстовые сообщения на признаки спама.
 */
class SpamChecker
{
    /*
     * @var array Список стоп-слов, которые исключаются из анализа текста.
     */
    private array $stopWords;

    /*
     * @var array Список заблокированных слов и выражений.
     */
    private array $blockList;

    /*
     * @var CacheInterface Кэш для хранения предыдущих сообщений и временных меток.
     */
    private CacheInterface $cache;

    /*
     * Конструктор класса SpamChecker.
     * @param string $stopWordsPath Путь к файлу со стоп-словами.
     * @param string $blockListPath Путь к файлу со списком заблокированных слов.
     * @param CacheInterface $cache Интерфейс для работы с кэшем.
     */
    public function __construct(string $stopWordsPath, string $blockListPath, CacheInterface $cache)
    {
        $this->stopWords = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->blockList = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->cache     = $cache;
    }

    /*
     * Метод проверяет текст на признаки спама
     */
    public function isSpam(string $text, bool $checkRate): array
    {
        $normalizedText       = $this->normalize($text);
        $normalizedTextString = implode(' ', $normalizedText);

        if ($this->containsBlockList($normalizedText)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'block_list',
                'normalized_text' => $normalizedTextString,
            ];
        }

        if ($this->containsMixedWords($normalizedText)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'mixed_words',
                'normalized_text' => $normalizedTextString,
            ];
        }

        if ($this->isDuplicate($normalizedTextString)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'duplicate',
                'normalized_text' => $normalizedTextString,
            ];
        }

        if ($checkRate && $this->checkRate()) {
            return [
                'status' => 'ok',
                'spam'   => true,
                'reason' => 'check_rate',
            ];
        }

        return [
            'status'          => 'ok',
            'spam'            => false,
            'reason'          => '',
            'normalized_text' => $normalizedTextString,
        ];
    }

    /*
     * Метод нормализует текст, удаляя стоп-слова и приводя к нижнему регистру.
     */
    private function normalize(string $text): array
    {
        $tokens = preg_split('/[\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+/', $text);
        $tokens = array_map('mb_strtolower', $tokens);
        $tokens = array_diff($tokens, $this->stopWords);
        $tokens = array_filter($tokens, fn($token) => !ctype_digit($token) && !empty($token));
        sort($tokens);

        return array_values($tokens);
    }

    /*
     * Метод проверяет, содержит ли текст заблокированные слова или адреса электронной почты
     */
    private function containsBlockList(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (in_array($token, $this->blockList) || filter_var($token, FILTER_VALIDATE_EMAIL)) {
                return true;
            }
        }

        return false;
    }

    /*
     * Метод проверяет, содержит ли текст слова, состоящие из смешанных алфавитов (кириллица и латиница)
     */
    private function containsMixedWords(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (preg_match('/[а-яА-Я]/u', $token) && preg_match('/[a-zA-Z]/', $token)) {
                return true;
            }
        }

        return false;
    }

    /*
     * Метод проверяет, является ли текст дубликатом одного из предыдущих сообщений.
     */
    private function isDuplicate(string $normalizedText): bool
    {
        $key              = 'spam_checker_previous_messages';
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

    /*
     * Метод проверяет скорость (частота) отправки сообщений.
     */
    private function checkRate(): bool
    {
        $key         = 'spam_checker_message_timestamps';
        $currentTime = microtime(true);

        $timestamps = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(2);

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

    /*
     * Метод очищает кэш сообщений и временных меток.
     */
    public function clearCache(): void
    {
        $this->cache->deleteItem('spam_checker_previous_messages');
        $this->cache->deleteItem('spam_checker_message_timestamps');
    }
}
