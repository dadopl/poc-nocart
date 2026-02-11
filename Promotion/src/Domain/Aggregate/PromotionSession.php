<?php

declare(strict_types=1);

namespace Nocart\Promotion\Domain\Aggregate;

final class PromotionSession
{
    /** @var array<string, array> */
    private array $appliedPromotions = [];

    /** @var array<string, array> */
    private array $appliedCodes = [];

    public function __construct(
        private readonly string $sessionId,
        private readonly string $userId
    ) {
    }

    public function applyPromotion(string $promotionId, string $promotionName, int $discountCents): void
    {
        $this->appliedPromotions[$promotionId] = [
            'id' => $promotionId,
            'name' => $promotionName,
            'discount_cents' => $discountCents,
            'applied_at' => date('c'),
        ];
    }

    public function applyPromoCode(string $code, string $promotionId, string $promotionName, int $discountCents): void
    {
        $this->appliedCodes[$code] = [
            'code' => $code,
            'promotion_id' => $promotionId,
            'name' => $promotionName,
            'discount_cents' => $discountCents,
            'applied_at' => date('c'),
        ];
        // Also add to applied promotions
        $this->applyPromotion($promotionId, $promotionName, $discountCents);
    }

    public function removePromotion(string $promotionId): void
    {
        unset($this->appliedPromotions[$promotionId]);
    }

    public function removePromoCode(string $code): void
    {
        if (isset($this->appliedCodes[$code])) {
            $promotionId = $this->appliedCodes[$code]['promotion_id'];
            unset($this->appliedCodes[$code]);
            unset($this->appliedPromotions[$promotionId]);
        }
    }

    public function hasPromotion(string $promotionId): bool
    {
        return isset($this->appliedPromotions[$promotionId]);
    }

    public function hasPromoCode(string $code): bool
    {
        return isset($this->appliedCodes[$code]);
    }

    public function getTotalDiscount(): int
    {
        return array_sum(array_column($this->appliedPromotions, 'discount_cents'));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getAppliedPromotions(): array
    {
        return array_values($this->appliedPromotions);
    }

    public function getAppliedCodes(): array
    {
        return array_values($this->appliedCodes);
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'applied_promotions' => $this->getAppliedPromotions(),
            'applied_codes' => $this->getAppliedCodes(),
            'total_discount' => $this->getTotalDiscount(),
        ];
    }
}
