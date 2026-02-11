<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Query;

use Nocart\Cart\Domain\Aggregate\Cart;
use Nocart\Cart\Domain\Repository\CartRepositoryInterface;
use Nocart\SharedKernel\Domain\ValueObject\CartId;
use Nocart\SharedKernel\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCartHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetCartQuery $query): array
    {
        $userId = UserId::fromString($query->userId);
        $cart = $this->cartRepository->findByUserId($userId);

        if ($cart === null) {
            $cart = Cart::create(
                id: CartId::generate(),
                userId: $userId,
            );
        }

        return $cart->toArray();
    }
}

