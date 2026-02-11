<?php

declare(strict_types=1);

namespace Nocart\Services\Domain\ValueObject;

final readonly class AdditionalService
{
    public function __construct(
        public int $id,
        public string $name,
        public Money $price,
        public string $category,
        public array $applicableCategories = []
    ) {
    }

    public function isApplicableToCategory(string $category): bool
    {
        if (empty($this->applicableCategories)) {
            return true; // Applies to all categories
        }

        return in_array($category, $this->applicableCategories, true);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price->toFloat(),
            'price_cents' => $this->price->amountInCents,
            'category' => $this->category,
            'applicable_categories' => $this->applicableCategories,
        ];
    }
}
