<?php

declare(strict_types=1);

namespace Nocart\Promotion\Infrastructure\Persistence;

use Nocart\Promotion\Domain\Aggregate\Promotion;
use Nocart\Promotion\Domain\Repository\PromotionRepositoryInterface;
use Nocart\Promotion\Domain\ValueObject\DiscountAmount;
use Nocart\Promotion\Domain\ValueObject\PromoCode;

final class InMemoryPromotionRepository implements PromotionRepositoryInterface
{
    /** @var Promotion[] */
    private array $promotions;

    /** @var array<string, Promotion> */
    private array $byCode = [];

    public function __construct()
    {
        $this->promotions = [
            new Promotion(
                id: 'promo-2x50',
                name: '2 w cenie 1.5',
                type: Promotion::TYPE_QUANTITY_DISCOUNT,
                discount: new DiscountAmount(DiscountAmount::TYPE_PERCENTAGE, 25),
                requiredQuantity: 2
            ),
            new Promotion(
                id: 'promo-quantity-2for1.5',
                name: '2 w cenie 1.5 (alt)',
                type: Promotion::TYPE_QUANTITY_DISCOUNT,
                discount: new DiscountAmount(DiscountAmount::TYPE_PERCENTAGE, 25),
                requiredQuantity: 2
            ),
            new Promotion(
                id: 'promo-code-save10',
                name: '10% rabatu - SAVE10',
                type: Promotion::TYPE_PROMO_CODE,
                discount: new DiscountAmount(DiscountAmount::TYPE_PERCENTAGE, 10),
                minCartValueCents: 10000, // 100 PLN
                promoCode: new PromoCode('SAVE10')
            ),
            new Promotion(
                id: 'promo-code-welcome10',
                name: '10% rabatu - WELCOME10',
                type: Promotion::TYPE_PROMO_CODE,
                discount: new DiscountAmount(DiscountAmount::TYPE_PERCENTAGE, 10),
                minCartValueCents: 10000, // 100 PLN
                promoCode: new PromoCode('WELCOME10')
            ),
            new Promotion(
                id: 'promo-code-freeship',
                name: 'Darmowa dostawa - FREESHIP',
                type: Promotion::TYPE_PROMO_CODE,
                discount: new DiscountAmount(DiscountAmount::TYPE_FIXED, 1500), // 15 PLN
                promoCode: new PromoCode('FREESHIP')
            ),
            new Promotion(
                id: 'promo-code-blackfriday',
                name: '20% rabatu - BLACKFRIDAY',
                type: Promotion::TYPE_PROMO_CODE,
                discount: new DiscountAmount(DiscountAmount::TYPE_PERCENTAGE, 20),
                minCartValueCents: 20000, // 200 PLN
                promoCode: new PromoCode('BLACKFRIDAY')
            ),
        ];

        // Index by code
        foreach ($this->promotions as $promotion) {
            if ($promotion->getPromoCode()) {
                $this->byCode[$promotion->getPromoCode()->code] = $promotion;
            }
        }
    }

    public function getAvailablePromotions(): array
    {
        return $this->promotions;
    }

    public function findByCode(string $code): ?Promotion
    {
        return $this->byCode[strtoupper($code)] ?? null;
    }

    public function findById(string $id): ?Promotion
    {
        foreach ($this->promotions as $promotion) {
            if ($promotion->getId() === $id) {
                return $promotion;
            }
        }

        return null;
    }
}
