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
     * @var int Пороговое значение процента совпадения для определения дубликатов.
     */
    private int $similarityThreshold;

    /*
     * @var float Интервал времени для проверки частоты отправки сообщений.
     */
    private int $timeInterval;

    /*
     * @var int Максимальное количество сообщений за интервал времени.
     */
    private int $maxMessages;

    /*
     * Конструктор класса SpamChecker.
     *
     * @param string $stopWordsPath - Путь к файлу со стоп-словами.
     * @param string $blockListPath - Путь к файлу со списком заблокированных слов.
     * @param CacheInterface $cache - Интерфейс для работы с кэшем.
     * @param int $similarityThreshold - Пороговое значение процента совпадения.
     * @param float $timeInterval - Интервал времени для проверки частоты отправки сообщений.
     * @param int $maxMessages - Максимальное количество сообщений за интервал времени.
     */
    public function __construct(
        string $stopWordsPath,
        string $blockListPath,
        CacheInterface $cache,
        int $similarityThreshold = 60,
        int $timeInterval = 2,
        int $maxMessages = 1
    ) {
        $this->stopWords           = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->blockList           = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->cache               = $cache;
        $this->similarityThreshold = $similarityThreshold;
        $this->timeInterval        = $timeInterval;
        $this->maxMessages         = $maxMessages;
    }

    /*
     * Метод проверяет текст на признаки спама.
     *
     * @param string $text Текст для проверки.
     * @param bool $checkRate Флаг проверки частоты отправки сообщений.
     * @return array Результат проверки спама.
     */
    public function isSpam(string $text, bool $checkRate): array
    {
        $normalizedText = $this->normalize($text);

        if ($this->containsBlockList($normalizedText)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'block_list',
                'normalized_text' => implode(' ', $normalizedText),
            ];
        }

        if ($this->containsMixedWords($normalizedText)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'mixed_words',
                'normalized_text' => implode(' ', $normalizedText),
            ];
        }

        if ($this->isDuplicate($normalizedText)) {
            return [
                'status'          => 'ok',
                'spam'            => true,
                'reason'          => 'duplicate',
                'normalized_text' => implode(' ', $normalizedText),
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
            'normalized_text' => implode(' ', $normalizedText),
        ];

    }

    /*
     * Метод нормализует текст, удаляя стоп-слова и приводя к нижнему регистру.
     *
     * @param string $text Текст для нормализации.
     * @return array Нормализованные токены.
     */
    private function normalize(string $text): array
    {
        preg_match_all('/[\w\.\-]+@[\w\.\-]+\.[\w]+/', $text, $matches);
        $emails = $matches[0];
        $text = preg_replace('/[\w\.\-]+@[\w\.\-]+\.[\w]+/', '', $text);
        $tokens = preg_split('/[\.\,\!\?\[\]\(\)\<\>\:\;\-\n\'\r\s\"\/\*\|]+/', $text);
        $tokens = array_map('mb_strtolower', $tokens);
        $tokens = array_diff($tokens, $this->stopWords);
        $tokens = array_filter($tokens, fn($token) => !ctype_digit($token) && !empty($token));
        $tokens = array_merge($tokens, $emails);
        sort($tokens);
        return array_values($tokens);
    }

    /*
     * Метод проверяет, содержит ли текст заблокированные слова или
     * адреса электронной почты.
     */
    private function containsBlockList(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (in_array(mb_strtolower($token), $this->blockList) || filter_var($token, FILTER_VALIDATE_EMAIL)) {
                return true;
            }
        }

        return false;
    }

    /*
     * Метод проверяет, содержит ли текст слова, состоящие из смешанных алфавитов (кириллица и латиница).
     *
     * @param array $tokens Нормализованные токены.
     * @return bool True, если содержит смешанные слова, иначе False.
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
     *
     * @param array $normalizedTokens Нормализованные токены.
     * @return bool True, если текст является дубликатом, иначе False.
     */
    private function isDuplicate(array $normalizedTokens): bool
    {
        if (count($normalizedTokens) < 3) {
            return false;
        }

        $key              = 'spam_checker_previous_messages';
        $previousMessages = $this->cache->get($key, function () {
            return [];
        });
        foreach ($previousMessages as $messageTokens) {
            $messageTokens = json_decode($messageTokens, true);

            $commonTokens = array_intersect($normalizedTokens, $messageTokens);
            $percent      = (count($commonTokens) / count($normalizedTokens)) * 100;

            if ($percent >= $this->similarityThreshold) {
                return true;
            }
        }

        $previousMessages[] = json_encode($normalizedTokens);

        if (count($previousMessages) > 10) {
            array_shift($previousMessages);
        }

        $this->cache->save($this->cache->getItem($key)->set($previousMessages)->expiresAfter(60));
        return false;
    }

    /*
     * Метод проверяет скорость (частоту) отправки сообщений.
     * @return bool True, если частота превышена, иначе False.
     */
    private function checkRate(): bool
    {
        $key = 'spam_checker_message_timestamps';
        $currentTime = microtime(true);

        $timestamps = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter($this->timeInterval);
            return [];
        });

        $timestamps[] = $currentTime;

        if (count($timestamps) > 1) {
            $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter($this->timeInterval));
            if (count($timestamps) < $this->maxMessages) {
                return false;
            }

            $timeDifference = $timestamps[count($timestamps) - 1] - $timestamps[0];

            if (count($timestamps) > $this->maxMessages) {
                array_shift($timestamps);
            }
            $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter($this->timeInterval));

            return $timeDifference < $this->timeInterval;
        }

        $this->cache->save($this->cache->getItem($key)->set($timestamps)->expiresAfter($this->timeInterval));
        return false;
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