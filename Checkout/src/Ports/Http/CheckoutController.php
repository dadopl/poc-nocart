<?php

declare(strict_types=1);

namespace Nocart\Checkout\Ports\Http;

use Nocart\Checkout\Application\Query\GetCheckoutTotalsHandler;
use Nocart\Checkout\Application\Query\GetCheckoutTotalsQuery;
use Nocart\Checkout\Domain\Aggregate\CheckoutSession;
use Nocart\Checkout\Domain\Repository\CheckoutSessionRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/checkout')]
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CheckoutSessionRepositoryInterface $sessionRepository
    ) {
    }

    #[Route('/totals', name: 'checkout_totals', methods: ['GET'])]
    public function getTotals(
        Request $request,
        GetCheckoutTotalsHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetCheckoutTotalsQuery($sessionId);
            $totals = $handler($query);

            $session = $this->sessionRepository->findBySessionId($sessionId);

            return new JsonResponse([
                'totals' => $totals,
                'can_finalize' => $session?->canFinalize() ?? false,
                'missing_requirements' => $session?->getMissingRequirements() ?? ['shipping_method', 'payment_method', 'customer_data', 'consents'],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    #[Route('/summary', name: 'checkout_summary', methods: ['GET'])]
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));
            $session = $this->sessionRepository->findBySessionId($sessionId);

            if (!$session) {
                return new JsonResponse([
                    'session' => ['status' => 'not_found'],
                ]);
            }

            return new JsonResponse([
                'session' => $session->toArray(),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/recalculate', name: 'checkout_recalculate', methods: ['POST'])]
    public function recalculate(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->headers->get('X-Session-Id', 'default');

            return new JsonResponse([
                'message' => 'Totals recalculated',
                'session_id' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/customer-data', name: 'checkout_customer_data', methods: ['POST'])]
    public function setCustomerData(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id', 'default');
            $userId = $request->headers->get('X-User-Id', 'guest');

            $session = $this->sessionRepository->findBySessionId($sessionId)
                ?? new CheckoutSession($sessionId, $userId);

            $session->setCustomerData([
                'email' => $payload['email'] ?? '',
                'first_name' => $payload['first_name'] ?? '',
                'last_name' => $payload['last_name'] ?? '',
                'phone' => $payload['phone'] ?? '',
                'company_name' => $payload['company_name'] ?? null,
                'tax_id' => $payload['tax_id'] ?? null,
            ]);

            $this->sessionRepository->save($session);

            return new JsonResponse([
                'message' => 'Customer data saved',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/consents', name: 'checkout_consents', methods: ['POST'])]
    public function setConsents(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true);
            $sessionId = $request->headers->get('X-Session-Id', 'default');
            $userId = $request->headers->get('X-User-Id', 'guest');

            $session = $this->sessionRepository->findBySessionId($sessionId)
                ?? new CheckoutSession($sessionId, $userId);

            $session->setConsents([
                'terms' => $payload['terms'] ?? false,
                'privacy' => $payload['privacy'] ?? false,
                'marketing' => $payload['marketing'] ?? false,
                'newsletter' => $payload['newsletter'] ?? false,
            ]);

            $this->sessionRepository->save($session);

            return new JsonResponse([
                'message' => 'Consents saved',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/finalize', name: 'checkout_finalize', methods: ['POST'])]
    public function finalize(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->headers->get('X-Session-Id', 'default');

            $session = $this->sessionRepository->findBySessionId($sessionId);

            if (!$session) {
                return new JsonResponse(['error' => 'Session not found'], 404);
            }

            if (!$session->canFinalize()) {
                return new JsonResponse([
                    'error' => 'Cannot finalize checkout',
                    'missing_requirements' => $session->getMissingRequirements(),
                ], 400);
            }

            $orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(8)));
            $session->finalize($orderId);
            $this->sessionRepository->save($session);

            return new JsonResponse([
                'message' => 'Checkout finalized',
                'order_id' => $orderId,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/complete-payment', name: 'checkout_complete_payment', methods: ['POST'])]
    public function completePayment(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->headers->get('X-Session-Id', 'default');

            $session = $this->sessionRepository->findBySessionId($sessionId);

            if (!$session) {
                return new JsonResponse(['error' => 'Session not found'], 404);
            }

            $session->complete();
            $this->sessionRepository->save($session);

            return new JsonResponse([
                'message' => 'Payment completed',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
