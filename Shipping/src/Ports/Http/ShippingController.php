<?php

declare(strict_types=1);

namespace Nocart\Shipping\Ports\Http;

use Nocart\Shipping\Application\Command\SelectShippingMethodCommand;
use Nocart\Shipping\Application\Command\SelectShippingMethodHandler;
use Nocart\Shipping\Application\Command\SetDeliveryDateCommand;
use Nocart\Shipping\Application\Command\SetDeliveryDateHandler;
use Nocart\Shipping\Application\Command\SetShippingAddressCommand;
use Nocart\Shipping\Application\Command\SetShippingAddressHandler;
use Nocart\Shipping\Application\Query\GetAvailableShippingMethodsHandler;
use Nocart\Shipping\Application\Query\GetAvailableShippingMethodsQuery;
use Nocart\Shipping\Application\Query\GetShippingSessionHandler;
use Nocart\Shipping\Application\Query\GetShippingSessionQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shipping')]
final class ShippingController extends AbstractController
{
    #[Route('/available', name: 'shipping_available', methods: ['GET'])]
    public function getAvailable(
        Request $request,
        GetAvailableShippingMethodsHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetAvailableShippingMethodsQuery($sessionId);
            $methods = $handler($query);

            return new JsonResponse(['methods' => $methods]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/method', name: 'shipping_select_method', methods: ['POST'])]
    #[Route('/select', name: 'shipping_select', methods: ['POST'])]
    public function selectMethod(
        Request $request,
        SelectShippingMethodHandler $handler
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';
            $correlationId = $request->headers->get('X-Correlation-Id');

            $command = new SelectShippingMethodCommand(
                sessionId: $sessionId,
                methodId: (string) ($payload['method_id'] ?? ''),
                userId: $userId,
                correlationId: $correlationId
            );

            $handler($command);

            return new JsonResponse([
                'message' => 'Shipping method selected',
            ], 202);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/address', name: 'shipping_set_address', methods: ['POST'])]
    public function setAddress(
        Request $request,
        SetShippingAddressHandler $handler
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';
            $correlationId = $request->headers->get('X-Correlation-Id');

            $command = new SetShippingAddressCommand(
                sessionId: $sessionId,
                street: $payload['street'] ?? '',
                city: $payload['city'] ?? '',
                postalCode: $payload['postal_code'] ?? '',
                country: $payload['country'] ?? 'PL',
                phoneNumber: $payload['phone_number'] ?? null,
                userId: $userId,
                correlationId: $correlationId
            );

            $handler($command);

            return new JsonResponse([
                'message' => 'Address saved',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/session', name: 'shipping_session', methods: ['GET'])]
    public function getSession(
        Request $request,
        GetShippingSessionHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetShippingSessionQuery($sessionId);
            $data = $handler($query);

            return new JsonResponse($data);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/set-date', name: 'shipping_set_date', methods: ['POST'])]
    public function setDeliveryDate(
        Request $request,
        SetDeliveryDateHandler $handler
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';
            $correlationId = $request->headers->get('X-Correlation-Id');

            $command = new SetDeliveryDateCommand(
                sessionId: $sessionId,
                deliveryDate: $payload['delivery_date'] ?? '',
                express: $payload['express'] ?? false,
                userId: $userId,
                correlationId: $correlationId
            );

            $handler($command);

            return new JsonResponse([
                'message' => 'Delivery date set',
                'delivery_date' => $payload['delivery_date'] ?? null,
                'express' => $payload['express'] ?? false,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/health', name: 'shipping_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
