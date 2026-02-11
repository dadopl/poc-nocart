<?php

declare(strict_types=1);

namespace Nocart\Services\Ports\Http;

use Nocart\Services\Application\Query\GetAvailableServicesHandler;
use Nocart\Services\Application\Query\GetAvailableServicesQuery;
use Nocart\Services\Application\Query\GetServicesForItemHandler;
use Nocart\Services\Application\Query\GetServicesForItemQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ServicesController extends AbstractController
{
    #[Route('/services/available', name: 'services_available', methods: ['GET'])]
    public function getAvailable(
        Request $request,
        GetAvailableServicesHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetAvailableServicesQuery($sessionId);
            $services = $handler($query);

            return new JsonResponse(['services' => $services]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/services/standalone', name: 'services_standalone', methods: ['GET'])]
    public function getStandalone(
        Request $request,
        GetAvailableServicesHandler $handler
    ): JsonResponse {
        try {
            $sessionId = $request->query->get('session_id', $request->headers->get('X-Session-Id', 'default'));

            $query = new GetAvailableServicesQuery($sessionId, standaloneOnly: true);
            $services = $handler($query);

            return new JsonResponse(['services' => $services]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/services/for-item/{offerId}', name: 'services_for_item', requirements: ['offerId' => '\d+'], methods: ['GET'])]
    public function getForItem(
        Request $request,
        GetServicesForItemHandler $handler,
        int $offerId
    ): JsonResponse {
        try {
            $category = $request->query->get('category', 'electronics');

            $query = new GetServicesForItemQuery($offerId, $category);
            $services = $handler($query);

            return new JsonResponse(['services' => $services]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/services/select', name: 'services_select', methods: ['POST'])]
    public function selectService(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Service selected',
        ], 202);
    }

    #[Route('/services/session', name: 'services_session', methods: ['GET'])]
    public function getSession(): JsonResponse
    {
        return new JsonResponse([
            'selected_services' => [],
            'total_cost' => 0,
        ]);
    }

    #[Route('/services/health', name: 'services_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
