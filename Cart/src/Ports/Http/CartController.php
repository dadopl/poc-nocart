<?php

declare(strict_types=1);

namespace Nocart\Cart\Ports\Http;

use Nocart\Cart\Application\Command\AddItemToCartCommand;
use Nocart\Cart\Application\Command\AddItemToCartHandler;
use Nocart\Cart\Application\Command\ChangeItemQuantityCommand;
use Nocart\Cart\Application\Command\ChangeItemQuantityHandler;
use Nocart\Cart\Application\Command\ClearCartCommand;
use Nocart\Cart\Application\Command\ClearCartHandler;
use Nocart\Cart\Application\Command\RemoveItemFromCartCommand;
use Nocart\Cart\Application\Command\RemoveItemFromCartHandler;
use Nocart\Cart\Application\Query\GetCartHandler;
use Nocart\Cart\Application\Query\GetCartQuery;
use Nocart\SharedKernel\Ports\Http\BaseApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends BaseApiController
{
    #[Route('', name: 'cart_get', methods: ['GET'])]
    public function getCart(Request $request, GetCartHandler $handler): JsonResponse
    {
        try {
            $userId = $this->getUserId($request);

            $result = $handler(new GetCartQuery(userId: $userId));

            return $this->jsonSuccess($result);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/items', name: 'cart_add_item', methods: ['POST'])]
    public function addItem(Request $request, AddItemToCartHandler $handler): JsonResponse
    {
        try {
            $userId = $this->getUserId($request);
            $payload = $this->getJsonPayload($request);

            $command = new AddItemToCartCommand(
                userId: $userId,
                offerId: (int) ($payload['offer_id'] ?? 0),
                type: $payload['type'] ?? 'product',
                name: $payload['name'] ?? 'Unknown',
                price: (float) ($payload['price'] ?? 0),
                quantity: (int) ($payload['quantity'] ?? 1),
                parentItemId: $payload['parent_item_id'] ?? null,
                correlationId: $request->headers->get('X-Correlation-Id'),
            );

            $itemId = $handler($command);

            return $this->jsonSuccess([
                'message' => 'Item added to cart',
                'item_id' => $itemId,
            ], 202);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/items/{itemId}', name: 'cart_remove_item', methods: ['DELETE'])]
    public function removeItem(
        string $itemId,
        Request $request,
        RemoveItemFromCartHandler $handler,
    ): JsonResponse {
        try {
            $userId = $this->getUserId($request);

            $handler(new RemoveItemFromCartCommand(
                userId: $userId,
                itemId: $itemId,
                correlationId: $request->headers->get('X-Correlation-Id'),
            ));

            return $this->jsonSuccess(['message' => 'Item removed']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/items/{itemId}', name: 'cart_change_quantity', methods: ['PATCH'])]
    public function changeQuantity(
        string $itemId,
        Request $request,
        ChangeItemQuantityHandler $handler,
    ): JsonResponse {
        try {
            $userId = $this->getUserId($request);
            $payload = $this->getJsonPayload($request);

            $handler(new ChangeItemQuantityCommand(
                userId: $userId,
                itemId: $itemId,
                quantity: (int) ($payload['quantity'] ?? 1),
                correlationId: $request->headers->get('X-Correlation-Id'),
            ));

            return $this->jsonSuccess(['message' => 'Quantity updated']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[Route('', name: 'cart_clear', methods: ['DELETE'])]
    public function clearCart(Request $request, ClearCartHandler $handler): JsonResponse
    {
        try {
            $userId = $this->getUserId($request);

            $handler(new ClearCartCommand(
                userId: $userId,
                correlationId: $request->headers->get('X-Correlation-Id'),
            ));

            return $this->jsonSuccess(['message' => 'Cart cleared']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/health', name: 'cart_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->jsonSuccess(['status' => 'ok', 'service' => 'cart']);
    }
}

