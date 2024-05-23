<?php
namespace App\Controller;

use App\Service\SpamChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\MessageNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

class SpamController extends AbstractController
{
    private MessageNormalizer $normalizer;
    private SpamChecker $checker;
    private CacheInterface $cache;

    public function __construct(MessageNormalizer $normalizer, SpamChecker $checker, CacheInterface $cache)
    {
        $this->normalizer = $normalizer;
        $this->checker = $checker;
        $this->checker->setCache($cache);
    }

    #[Route('/is_spam', name: 'spam', methods: ['POST'])]
    public function isSpam(Request $request): JsonResponse
    {
        $text = $request->request->get('text');
        $checkRate = $request->request->get('check_rate', 0);

        if (empty($text)) {
            return new JsonResponse(json_encode(
                [
                    'status' => 'error',
                    'message' => 'field text required'
                ]), 400);
        }

        $tokens = $this->normalizer->normalize($text);
        $result = $this->checker->isSpam($tokens, $text, (bool)$checkRate);


        $response = [
            'status' => 'ok',
            'normalized_text' => implode(' ', $tokens),
            'spam' => $result['spam'],
            'reason' => $result['reason'] ?? ''
        ];

        return new JsonResponse($response, 200);

    }
    /*
     *  Для проверки кэширования - временное использование
     * */
    #[Route('/cache_content', name: 'cache_content', methods: ['GET'])]
    public function getCacheContent(Request $request): JsonResponse
    {
        $key = $request->query->get('key');

        if (empty($key)) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'message' => 'field key required'
                ], 400);
        }

        $content = $this->checker->getCacheContent($key);

        return new JsonResponse(
            [
                'status' => 'ok',
                'content' => $content
            ], 200);
    }

}