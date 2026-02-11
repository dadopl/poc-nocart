<?php

declare(strict_types=1);

namespace Nocart\Promotion\Ports\Http;

use Nocart\Promotion\Application\Command\ApplyPromoCodeCommand;
use Nocart\Promotion\Application\Command\ApplyPromoCodeHandler;
use Nocart\Promotion\Application\Command\ApplyPromotionCommand;
use Nocart\Promotion\Application\Command\ApplyPromotionHandler;
use Nocart\Promotion\Application\Query\GetAvailablePromotionsHandler;
use Nocart\Promotion\Application\Query\GetAvailablePromotionsQuery;
use Nocart\Promotion\Application\Query\GetPromotionSessionHandler;
use Nocart\Promotion\Application\Query\GetPromotionSessionQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/promotions')]
final class PromotionController extends AbstractController
{
    #[Route('/available', name: 'promotions_available', methods: ['GET'])]
    public function getAvailable(
        Request $request,
        GetAvailablePromotionsHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetAvailablePromotionsQuery($sessionId);
            $promotions = $handler($query);

            return new JsonResponse(['promotions' => $promotions]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/apply', name: 'promotions_apply', methods: ['POST'])]
    public function apply(
        Request $request,
        ApplyPromotionHandler $handler
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';
            $correlationId = $request->headers->get('X-Correlation-Id');

            $command = new ApplyPromotionCommand(
                sessionId: $sessionId,
                promotionId: $payload['promotion_id'] ?? '',
                userId: $userId,
                cartTotalCents: (int) ($payload['cart_total_cents'] ?? 0),
                itemQuantity: (int) ($payload['item_quantity'] ?? 1),
                correlationId: $correlationId
            );

            $result = $handler($command);

            return new JsonResponse($result, 202);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/apply-code', name: 'promotions_apply_code', methods: ['POST'])]
    public function applyCode(
        Request $request,
        ApplyPromoCodeHandler $handler
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';
            $correlationId = $request->headers->get('X-Correlation-Id');

            $command = new ApplyPromoCodeCommand(
                sessionId: $sessionId,
                code: $payload['code'] ?? '',
                userId: $userId,
                cartTotalCents: (int) ($payload['cart_total_cents'] ?? $payload['order_total_cents'] ?? 0),
                correlationId: $correlationId
            );

            $result = $handler($command);

            return new JsonResponse($result, 202);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/session', name: 'promotions_session', methods: ['GET'])]
    public function getSession(
        Request $request,
        GetPromotionSessionHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetPromotionSessionQuery($sessionId);
            $data = $handler($query);

            return new JsonResponse($data);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/health', name: 'promotions_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
