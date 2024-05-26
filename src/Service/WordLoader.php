<?php
namespace App\Service;

class WordLoader
{
    private array $stopWords;
    private array $blockList;
//
//    public function __construct(string $stopWordsPath, string $blockListPath)
//    {
//        $this->stopWords = file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//        $this->blockList = file($blockListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//    }

    public function __construct(string $stopWordsPath, string $blockListPath)
    {
        $this->stopWords = $this->loadWords($stopWordsPath);
        $this->blockList = $this->loadWords($blockListPath);
    }

    private function loadWords(string $path): array
    {
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public function getStopWords(): array
    {
        return $this->stopWords;
    }

    public function getBlockList(): array
    {
        return $this->blockList;
    }
}
