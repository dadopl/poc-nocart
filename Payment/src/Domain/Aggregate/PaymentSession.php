<?php

declare(strict_types=1);

namespace Nocart\Payment\Domain\Aggregate;

use Nocart\Payment\Domain\ValueObject\PaymentMethod;
use Nocart\Payment\Domain\ValueObject\PaymentStatus;

final class PaymentSession
{
    private ?PaymentMethod $selectedMethod = null;
    private PaymentStatus $status;
    private ?string $transactionId = null;
    private int $amount = 0;

    public function __construct(
        private readonly string $sessionId,
        private readonly string $userId
    ) {
        $this->status = PaymentStatus::PENDING;
    }

    public function selectMethod(PaymentMethod $method): void
    {
        $this->selectedMethod = $method;
    }

    public function processPayment(int $amount): void
    {
        $this->amount = $amount;
        $this->status = PaymentStatus::PROCESSING;
    }

    public function confirmPayment(string $transactionId): void
    {
        $this->transactionId = $transactionId;
        $this->status = PaymentStatus::SUCCEEDED;
    }

    public function failPayment(): void
    {
        $this->status = PaymentStatus::FAILED;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getSelectedMethod(): ?PaymentMethod
    {
        return $this->selectedMethod;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'selected_method' => $this->selectedMethod?->id,
            'selected_method_data' => $this->selectedMethod?->toArray(),
            'status' => $this->status->value,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
        ];
    }
}
