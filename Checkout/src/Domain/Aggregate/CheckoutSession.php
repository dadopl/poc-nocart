<?php

declare(strict_types=1);

namespace Nocart\Checkout\Domain\Aggregate;

final class CheckoutSession
{
    private string $sessionId;
    private string $userId;
    private string $status;
    private ?string $orderId = null;
    private ?array $customerData = null;
    private ?array $consents = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $sessionId,
        string $userId,
        string $status = 'pending'
    ) {
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getCustomerData(): ?array
    {
        return $this->customerData;
    }

    public function getConsents(): ?array
    {
        return $this->consents;
    }

    public function setCustomerData(array $data): void
    {
        $this->customerData = $data;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setConsents(array $consents): void
    {
        $this->consents = $consents;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function finalize(string $orderId): void
    {
        $this->orderId = $orderId;
        $this->status = 'finalized';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function canFinalize(): bool
    {
        return $this->customerData !== null
            && $this->consents !== null
            && ($this->consents['terms'] ?? false) === true
            && ($this->consents['privacy'] ?? false) === true;
    }

    public function getMissingRequirements(): array
    {
        $missing = [];

        if ($this->customerData === null) {
            $missing[] = 'customer_data';
        }

        if ($this->consents === null || !($this->consents['terms'] ?? false) || !($this->consents['privacy'] ?? false)) {
            $missing[] = 'consents';
        }

        return $missing;
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'status' => $this->status,
            'order_id' => $this->orderId,
            'customer_data' => $this->customerData,
            'consents' => $this->consents,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function fromArray(array $data): self
    {
        $session = new self(
            $data['session_id'],
            $data['user_id'],
            $data['status'] ?? 'pending'
        );

        $session->orderId = $data['order_id'] ?? null;
        $session->customerData = $data['customer_data'] ?? null;
        $session->consents = $data['consents'] ?? null;
        $session->createdAt = new \DateTimeImmutable($data['created_at'] ?? 'now');
        $session->updatedAt = new \DateTimeImmutable($data['updated_at'] ?? 'now');

        return $session;
    }
}
