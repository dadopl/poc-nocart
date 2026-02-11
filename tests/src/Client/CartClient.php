<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

final class CartClient extends BaseServiceClient
{
    /** @return array<string, mixed> */
    public function getCart(): array
    {
        $response = $this->httpClient->request('GET', '/cart', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function addItem(
        int $offerId,
        string $type,
        int $quantity = 1,
        float $price = 0.0,
        ?string $parentItemId = null,
        ?string $serviceId = null,
        ?string $correlationId = null,
    ): array {
        $payload = [
            'offer_id' => $offerId,
            'type' => $type,
            'quantity' => $quantity,
            'price' => $price,
        ];

        if ($parentItemId !== null) {
            $payload['parent_item_id'] = $parentItemId;
        }

        if ($serviceId !== null) {
            $payload['service_id'] = $serviceId;
        }

        $response = $this->httpClient->request('POST', '/cart/items', [
            'headers' => $this->getHeaders($correlationId),
            'json' => $payload,
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function removeItem(string $itemId, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('DELETE', '/cart/items/' . $itemId, [
            'headers' => $this->getHeaders($correlationId),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function changeQuantity(string $itemId, int $quantity, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('PATCH', '/cart/items/' . $itemId, [
            'headers' => $this->getHeaders($correlationId),
            'json' => ['quantity' => $quantity],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function clearCart(?string $correlationId = null): array
    {
        $response = $this->httpClient->request('DELETE', '/cart', [
            'headers' => $this->getHeaders($correlationId),
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '/cart/health');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}

