<?php
namespace App\Controller;

use App\Service\SpamChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\MessageNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SpamController extends AbstractController
{
    private $normalizer;
    private $checker;

    public function __construct(MessageNormalizer $normalizer, SpamChecker $checker)
    {
        $this->normalizer = $normalizer;
        $this->checker = $checker;
    }

    #[Route('/is_spam', name: 'spam', methods: ['POST'])]
    public function isSpam(Request $request): JsonResponse
    {
        $text = $request->request->get('text');

        if (empty($text)) {
            return new JsonResponse(json_encode(['status' => 'error', 'message' => 'field text required']), 400);
        }

        $tokens = $this->normalizer->normalize($text);
        $result = $this->checker->isSpam($tokens, $text);
        return new JsonResponse(json_encode(array_merge(
            [
                'status' => 'ok',
                'spam' => $result,
                'reason' => $result ? 'block_list' : '',
                'normalized_text' => implode(' ', $tokens)], $result
            )), 200);
    }

}