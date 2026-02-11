<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

final class PaymentClient extends BaseServiceClient
{
    /** @return array<string, mixed> */
    public function getAvailableMethods(): array
    {
        $response = $this->httpClient->request('GET', '/payment/available', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function selectMethod(string $methodId, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/payment/select', [
            'headers' => $this->getHeaders($correlationId),
            'json' => ['method_id' => $methodId],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function initializePayment(
        string $orderId,
        int $amountCents,
        string $currency = 'PLN',
        ?string $correlationId = null,
    ): array {
        $response = $this->httpClient->request('POST', '/payment/initialize', [
            'headers' => $this->getHeaders($correlationId),
            'json' => [
                'order_id' => $orderId,
                'amount_cents' => $amountCents,
                'currency' => $currency,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function confirmPayment(string $code, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/payment/confirm', [
            'headers' => $this->getHeaders($correlationId),
            'json' => ['code' => $code],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getStatus(): array
    {
        $response = $this->httpClient->request('GET', '/payment/status', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '/payment/health');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}

