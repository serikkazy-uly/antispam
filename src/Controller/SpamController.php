<?php
namespace App\Controller;

//use App\Service\SpamChecker;
//use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
//use App\Service\MessageNormalizer;
//use Symfony\Component\HttpFoundation\JsonResponse;
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\Routing\Annotation\Route;
//use Symfony\Contracts\Cache\CacheInterface;

//class SpamController extends AbstractController
//{
//    private MessageNormalizer $normalizer;
//    private SpamChecker $checker;
//    private CacheInterface $cache;
//
//
//    public function __construct(MessageNormalizer $normalizer, SpamChecker $checker, CacheInterface $cache)
//    {
//        $this->normalizer = $normalizer;
//        $this->checker = $checker;
//        $this->checker->setCache($cache);
//        $this->cache = $cache;
//    }
//
//
//    #[Route('/is_spam', name: 'spam', methods: ['POST'])]
//    public function isSpam(Request $request): JsonResponse
//    {
//        $text = $request->request->get('text', '');
//        $checkRate = $request->request->get('check_rate', false);
//
//        if (empty($text)) {
//            return $this->json([
//                'status' => 'error',
//                'message' => 'Text is required'
//            ], JsonResponse::HTTP_BAD_REQUEST);
//        }
////        if (empty($text)) {
////            return new JsonResponse([
////                'status' => 'error',
////                'message' => 'field text required'
//////                'message' => 'Text is required'
////            ], 400);
////        }
//
////        $checkRate = $data['check_rate'] ?? false;
//
////        $filteredTokens = $this->normalizer->normalize($text);
////        $normalizedText = implode(' ', $filteredTokens);
//
////        $result = $this->checker->isSpam($filteredTokens, $normalizedText, $checkRate);
////
////        $result['normalized_text'] = $filteredTokens;
////        return new JsonResponse($result);
//        $result = $this->checker->isSpam($text, $checkRate);
//
//        return $this->json($result);
//    }
//
//    #[Route('/invalidate_cache', name: 'invalidate_cache', methods: ['POST'])]
//    public function invalidateCache(): JsonResponse
//    {
//        $this->checker->clearCache();
//        return new JsonResponse(['status' => 'ok']);
//    }
//
//    #[Route('/cache_content', name: 'cache_content', methods: ['GET'])]
//    public function getCacheContent(Request $request): JsonResponse
//    {
//        $key = $request->query->get('key');
//
//        if (empty($key)) {
//            return new JsonResponse(
//                [
//                    'status' => 'error',
//                    'message' => 'field key required'
//                ], 400);
//        }
//
//        $content = $this->checker->getCacheContent($key);
//
//        return new JsonResponse(
//            [
//                'status' => 'ok',
//                'content' => $content
//            ], 200);
//    }
//
//
//
//}

//    #[Route('/is_spam', name: 'spam', methods: ['POST'])]
//    public function isSpam(Request $request): JsonResponse
//    {
//        $text = $request->request->get('text');
//        $checkRate = $request->request->get('check_rate', 0);
////        $data = json_decode($request->getContent(), true);
////        $text = $data['text'] ?? '';
//
//        if (empty($text)) {
//            return new JsonResponse(
//                [
//                    'status' => 'error',
//                    'message' => 'field text required',
//                ], 400);
//        }
//
//        $tokens = $this->normalizer->normalize($text);
//        $result = $this->checker->isSpam($tokens, $text, (bool)$checkRate);
//
//        $response = [
//            'status' => 'ok',
//            //'normalized_text' => implode(' ', $tokens),
//            'spam' => $result['spam'],
//            'reason' => $result['reason'] ?? '',
//        ];
//
//        return new JsonResponse($response, 200);
//
//    }


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\SpamChecker;

class SpamController extends AbstractController
{
    private SpamChecker $spamChecker;

    public function __construct(SpamChecker $spamChecker)
    {
        $this->spamChecker = $spamChecker;
    }

    #[Route('/is_spam', name: 'spam', methods: ['POST'])]
    public function isSpam(Request $request): Response
    {
        $text = $request->request->get('text');
        $checkRate = (bool) $request->request->get('check_rate', false);

        if (empty($text)) {
            return $this->json([
                'status' => 'error',
                'message' => 'field text required'
            ], 400);
        }

        $result = $this->spamChecker->isSpam($text, $checkRate);

        return $this->json($result);
    }


    #[Route('/invalidate_cache', name: 'invalidate_cache', methods: ['POST'])]
    public function invalidateCache(): Response
    {
        $this->spamChecker->clearCache();

        return $this->json([
            'status' => 'ok'
        ]);
    }
}
