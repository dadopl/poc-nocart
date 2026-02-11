<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

final class ShippingClient extends BaseServiceClient
{
    /** @return array<string, mixed> */
    public function getAvailableMethods(): array
    {
        $response = $this->httpClient->request('GET', '/shipping/available', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function selectMethod(string $methodId, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/shipping/select', [
            'headers' => $this->getHeaders($correlationId),
            'json' => ['method_id' => $methodId],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function setAddress(
        string $street,
        string $city,
        string $postalCode,
        string $country = 'PL',
        ?string $correlationId = null,
    ): array {
        $response = $this->httpClient->request('POST', '/shipping/address', [
            'headers' => $this->getHeaders($correlationId),
            'json' => [
                'street' => $street,
                'city' => $city,
                'postal_code' => $postalCode,
                'country' => $country,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function setDeliveryDate(string $date, bool $express = false, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/shipping/set-date', [
            'headers' => $this->getHeaders($correlationId),
            'json' => [
                'delivery_date' => $date,
                'express' => $express,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getSession(): array
    {
        $response = $this->httpClient->request('GET', '/shipping/session', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '/shipping/health');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}

