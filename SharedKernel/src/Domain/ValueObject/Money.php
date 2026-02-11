<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\ValueObject;

final readonly class Money
{
    private function __construct(
        private int $amount,
        private string $currency = 'PLN',
    ) {
    }

    public static function fromFloat(float $amount, string $currency = 'PLN'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public static function fromCents(int $cents, string $currency = 'PLN'): self
    {
        return new self($cents, $currency);
    }

    public static function zero(string $currency = 'PLN'): self
    {
        return new self(0, $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int|float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->amount < $other->amount;
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                sprintf('Cannot operate on different currencies: %s and %s', $this->currency, $other->currency)
            );
        }
    }

    /** @return array{amount: int, currency: string} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    /** @param array{amount: int, currency: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['amount'], $data['currency']);
    }
}

