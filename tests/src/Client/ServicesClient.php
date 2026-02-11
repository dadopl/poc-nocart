<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

final class ServicesClient extends BaseServiceClient
{
    /** @return array<string, mixed> */
    public function getAvailableServices(): array
    {
        $response = $this->httpClient->request('GET', '/services/available', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getServicesForItem(int $offerId, string $category = 'electronics'): array
    {
        $response = $this->httpClient->request('GET', '/services/for-item/' . $offerId, [
            'headers' => $this->getHeaders(),
            'query' => ['category' => $category],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getStandaloneServices(): array
    {
        $response = $this->httpClient->request('GET', '/services/standalone', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function selectService(string $serviceId, ?string $parentItemId = null, ?string $correlationId = null): array
    {
        $payload = ['service_id' => $serviceId];

        if ($parentItemId !== null) {
            $payload['parent_item_id'] = $parentItemId;
        }

        $response = $this->httpClient->request('POST', '/services/select', [
            'headers' => $this->getHeaders($correlationId),
            'json' => $payload,
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getSession(): array
    {
        $response = $this->httpClient->request('GET', '/services/session', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '/services/health');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}

