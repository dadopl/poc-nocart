<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Ports\Http;

use Nocart\SharedKernel\Application\DTO\ApiResponse;
use Nocart\SharedKernel\Domain\Exception\DomainException;
use Nocart\SharedKernel\Domain\Exception\NotFoundException;
use Nocart\SharedKernel\Domain\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseApiController extends AbstractController
{
    protected function jsonSuccess(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            ApiResponse::success(is_array($data) ? $data : ['result' => $data], $status)->toArray(),
            $status,
        );
    }

    protected function jsonAccepted(string $message = 'Accepted'): JsonResponse
    {
        return new JsonResponse(
            ApiResponse::accepted($message)->toArray(),
            202,
        );
    }

    protected function jsonError(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(
            ApiResponse::error($message, $status)->toArray(),
            $status,
        );
    }

    protected function handleException(\Throwable $e): JsonResponse
    {
        if ($e instanceof NotFoundException) {
            return $this->jsonError($e->getMessage(), 404);
        }

        if ($e instanceof ValidationException) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors,
            ], 422);
        }

        if ($e instanceof DomainException) {
            return $this->jsonError($e->getMessage(), 400);
        }

        // W środowisku dev zwracaj pełne informacje o błędzie
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ], 500);
    }

    protected function getSessionId(Request $request): string
    {
        $sessionId = $request->headers->get('X-Session-Id');

        if ($sessionId === null || $sessionId === '') {
            throw new ValidationException('X-Session-Id header is required');
        }

        return $sessionId;
    }

    protected function getUserId(Request $request): string
    {
        $userId = $request->headers->get('X-User-Id');

        if ($userId === null || $userId === '') {
            throw new ValidationException('X-User-Id header is required');
        }

        return $userId;
    }

    /** @return array<string, mixed> */
    protected function getJsonPayload(Request $request): array
    {
        $content = $request->getContent();

        if ($content === '') {
            return [];
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException('Invalid JSON payload');
        }
    }
}

