<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\ValueObject;

final readonly class Quantity
{
    private function __construct(
        private int $value,
    ) {
        if ($value < 0) {
            throw new \InvalidArgumentException('Quantity cannot be negative');
        }
    }

    public static function of(int $value): self
    {
        return new self($value);
    }

    public static function one(): self
    {
        return new self(1);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(self $other): self
    {
        $newValue = $this->value - $other->value;
        if ($newValue < 0) {
            throw new \InvalidArgumentException('Cannot subtract: result would be negative');
        }

        return new self($newValue);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->value * $multiplier);
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function greaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }
}

