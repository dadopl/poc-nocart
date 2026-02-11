<?php

declare(strict_types=1);

namespace Nocart\Promotion\Domain\ValueObject;

final readonly class PromoCode
{
    public function __construct(
        public string $code,
        public ?\DateTimeImmutable $expiryDate = null,
        public ?int $usageLimit = null,
        public int $usedCount = 0
    ) {
    }

    public function isExpired(): bool
    {
        if ($this->expiryDate === null) {
            return false;
        }

        return $this->expiryDate < new \DateTimeImmutable();
    }

    public function isUsageLimitReached(): bool
    {
        if ($this->usageLimit === null) {
            return false;
        }

        return $this->usedCount >= $this->usageLimit;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsageLimitReached();
    }
}
