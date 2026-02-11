<?php

declare(strict_types=1);

namespace Nocart\Services\Domain\Aggregate;

final class ServicesSession
{
    /** @var array<int, array> */
    private array $selectedServices = [];

    public function __construct(
        private readonly string $sessionId,
        private readonly string $userId
    ) {
    }

    public function selectService(int $serviceId, string $serviceName, int $priceCents): void
    {
        $this->selectedServices[$serviceId] = [
            'id' => $serviceId,
            'name' => $serviceName,
            'price_cents' => $priceCents,
            'selected_at' => date('c'),
        ];
    }

    public function removeService(int $serviceId): void
    {
        unset($this->selectedServices[$serviceId]);
    }

    public function getTotalCost(): int
    {
        return array_sum(array_column($this->selectedServices, 'price_cents'));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getSelectedServices(): array
    {
        return array_values($this->selectedServices);
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'selected_services' => $this->getSelectedServices(),
            'total_cost' => $this->getTotalCost(),
        ];
    }
}
