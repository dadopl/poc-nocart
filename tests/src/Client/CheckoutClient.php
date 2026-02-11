<?php

declare(strict_types=1);

namespace Nocart\E2E\Client;

final class CheckoutClient extends BaseServiceClient
{
    /** @return array<string, mixed> */
    public function getSummary(): array
    {
        $response = $this->httpClient->request('GET', '/checkout/summary', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function getTotals(): array
    {
        $response = $this->httpClient->request('GET', '/checkout/totals', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function recalculateTotals(?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/checkout/recalculate', [
            'headers' => $this->getHeaders($correlationId),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function setCustomerData(
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
        ?string $companyName = null,
        ?string $taxId = null,
        ?string $correlationId = null,
    ): array {
        $payload = [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ];

        if ($companyName !== null) {
            $payload['company_name'] = $companyName;
        }

        if ($taxId !== null) {
            $payload['tax_id'] = $taxId;
        }

        $response = $this->httpClient->request('POST', '/checkout/customer-data', [
            'headers' => $this->getHeaders($correlationId),
            'json' => $payload,
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function setConsents(
        bool $terms,
        bool $privacy,
        bool $marketing = false,
        bool $newsletter = false,
        ?string $correlationId = null,
    ): array {
        $response = $this->httpClient->request('POST', '/checkout/consents', [
            'headers' => $this->getHeaders($correlationId),
            'json' => [
                'terms' => $terms,
                'privacy' => $privacy,
                'marketing' => $marketing,
                'newsletter' => $newsletter,
            ],
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function finalize(?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/checkout/finalize', [
            'headers' => $this->getHeaders($correlationId),
        ]);

        return $this->parseResponse($response->getContent());
    }

    /** @return array<string, mixed> */
    public function completePayment(string $transactionId, ?string $correlationId = null): array
    {
        $response = $this->httpClient->request('POST', '/checkout/complete-payment', [
            'headers' => $this->getHeaders($correlationId),
            'json' => ['transaction_id' => $transactionId],
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function health(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '/checkout/health');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}

