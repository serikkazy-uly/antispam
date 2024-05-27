<?php
declare(strict_types=1);
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\SpamChecker;

/*
 * Контроллер для проверки сообщений на спам и очистки кэша.
 */
class SpamController extends AbstractController
{
    private SpamChecker $spamChecker;

    /*
     * Конструктор контроллера SpamController
     */
    public function __construct(SpamChecker $spamChecker)
    {
        $this->spamChecker = $spamChecker;
    }

    /**
     * Метод проверяет текст на спам
     */
    #[Route('/is_spam', name: 'spam', methods: ['POST'])]
    public function isSpam(Request $request): JsonResponse
    {
        $text      = $request->request->get('text');
        $checkRate = (bool)$request->request->get('check_rate', false);

        if (empty($text)) {
            return $this->json([
                'status'  => 'error',
                'message' => 'field text required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->spamChecker->isSpam($text, $checkRate);

        return $this->json($result);
    }

    /*
     * Метод очищает кэш сообщений
     */
    #[Route('/invalidate_cache', name: 'invalidate_cache', methods: ['POST'])]
    public function invalidateCache(): JsonResponse
    {
        $this->spamChecker->clearCache();

        return $this->json([
            'status' => 'ok',
        ]);
    }
}
