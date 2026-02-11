<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

final class PromotionClient extends BaseServiceClient
{
    /** @return array<string, mixed> */
    public function getAvailablePromotions(int $cartTotalCents = 0, int $itemQuantity = 1): array
    {
        $response = $this->httpClient->request('GET', '/promotions/available', [
            'headers' => $this->getHeaders(),
            'query' => [
                'cart_total_cents' => $cartTotalCents,
                'item_quantity' => $itemQuantity,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function applyPromotion(
        string $promotionId,
        int $cartTotalCents,
        int $itemQuantity = 1,
        ?string $correlationId = null,
    ): array {
        $response = $this->httpClient->request('POST', '/promotions/apply', [
            'headers' => $this->getHeaders($correlationId),
            'json' => [
                'promotion_id' => $promotionId,
                'cart_total_cents' => $cartTotalCents,
                'item_quantity' => $itemQuantity,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function removePromotion(string $promotionId, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('DELETE', '/promotions/' . $promotionId, [
            'headers' => $this->getHeaders($correlationId),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function applyPromoCode(string $code, int $orderTotalCents, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/promotions/apply-code', [
            'headers' => $this->getHeaders($correlationId),
            'json' => [
                'code' => $code,
                'order_total_cents' => $orderTotalCents,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getSession(): array
    {
        $response = $this->httpClient->request('GET', '/promotions/session', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '/promotions/health');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}

