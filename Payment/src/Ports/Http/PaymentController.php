<?php

declare(strict_types=1);

namespace Nocart\Payment\Ports\Http;

use Nocart\Payment\Application\Command\SelectPaymentMethodCommand;
use Nocart\Payment\Application\Command\SelectPaymentMethodHandler;
use Nocart\Payment\Application\Query\GetAvailablePaymentMethodsHandler;
use Nocart\Payment\Application\Query\GetAvailablePaymentMethodsQuery;
use Nocart\Payment\Application\Query\GetPaymentStatusHandler;
use Nocart\Payment\Application\Query\GetPaymentStatusQuery;
use Nocart\Payment\Domain\Aggregate\PaymentSession;
use Nocart\Payment\Domain\Repository\PaymentSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payment')]
final class PaymentController extends AbstractController
{
    #[Route('/available', name: 'payment_available', methods: ['GET'])]
    public function getAvailable(
        Request $request,
        GetAvailablePaymentMethodsHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetAvailablePaymentMethodsQuery($sessionId);
            $methods = $handler($query);

            return new JsonResponse(['methods' => $methods]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/method', name: 'payment_select_method', methods: ['POST'])]
    #[Route('/select', name: 'payment_select', methods: ['POST'])]
    public function selectMethod(
        Request $request,
        SelectPaymentMethodHandler $handler
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';
            $correlationId = $request->headers->get('X-Correlation-Id');

            $command = new SelectPaymentMethodCommand(
                sessionId: $sessionId,
                methodId: (string) ($payload['method_id'] ?? ''),
                userId: $userId,
                correlationId: $correlationId
            );

            $handler($command);

            return new JsonResponse([
                'message' => 'Payment method selected',
            ], 202);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/status', name: 'payment_status', methods: ['GET'])]
    public function getStatus(
        Request $request,
        GetPaymentStatusHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetPaymentStatusQuery($sessionId);
            $data = $handler($query);

            return new JsonResponse($data);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/initialize', name: 'payment_initialize', methods: ['POST'])]
    public function initialize(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';

            $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(8)));

            return new JsonResponse([
                'message' => 'Payment initialized',
                'transaction_id' => $transactionId,
                'order_id' => $payload['order_id'] ?? null,
                'amount_cents' => $payload['amount_cents'] ?? 0,
                'currency' => $payload['currency'] ?? 'PLN',
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/confirm', name: 'payment_confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        PaymentSessionRepositoryInterface $sessionRepository,
        RedisClientInterface $redis
    ): JsonResponse {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id') ?? $payload['session_id'] ?? 'default';
            $userId = $request->headers->get('X-User-Id') ?? $payload['user_id'] ?? 'guest';

            $session = $sessionRepository->findBySessionId($sessionId)
                ?? new PaymentSession($sessionId, $userId);

            $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(8)));
            $session->confirmPayment($transactionId);
            $sessionRepository->save($session);

            // Update checkout session status to 'completed'
            $checkoutKey = "checkout:session:{$sessionId}";
            $checkoutData = $redis->get($checkoutKey);
            if ($checkoutData) {
                $checkoutArray = json_decode($checkoutData, true);
                $checkoutArray['status'] = 'completed';
                $checkoutArray['updated_at'] = date('c');
                $redis->set($checkoutKey, json_encode($checkoutArray), 86400);
            }

            return new JsonResponse([
                'message' => 'Payment confirmed',
                'status' => 'succeeded',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/health', name: 'payment_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
