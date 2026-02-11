<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class BaseServiceClient
{
    protected HttpClientInterface $httpClient;
    protected string $sessionId;
    protected string $userId;

    public function __construct(
        protected readonly string $baseUrl,
        string $sessionId,
        string $userId,
    ) {
        $this->httpClient = HttpClient::create([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
        $this->sessionId = $sessionId;
        $this->userId = $userId;
    }

    /** @return array<string, string> */
    protected function getHeaders(?string $correlationId = null): array
    {
        $headers = [
            'X-Session-Id' => $this->sessionId,
            'X-User-Id' => $this->userId,
        ];

        if ($correlationId !== null) {
            $headers['X-Correlation-Id'] = $correlationId;
        }

        return $headers;
    }

    /** @return array<string, mixed> */
    protected function parseResponse(string $content): array
    {
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        return $data['data'] ?? $data;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}

