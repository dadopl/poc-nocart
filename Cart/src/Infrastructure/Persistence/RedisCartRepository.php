<?php

declare(strict_types=1);

namespace Nocart\Cart\Infrastructure\Persistence;

use Nocart\Cart\Domain\Aggregate\Cart;
use Nocart\Cart\Domain\Repository\CartRepositoryInterface;
use Nocart\SharedKernel\Domain\ValueObject\UserId;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisCartRepository implements CartRepositoryInterface
{
    private const KEY_PREFIX = 'cart:';
    private const TTL = 86400 * 7; // 7 days

    public function __construct(
        private RedisClientInterface $redis,
    ) {
    }

    public function findByUserId(UserId $userId): ?Cart
    {
        $key = $this->getKey($userId);
        $data = $this->redis->get($key);

        if ($data === null) {
            return null;
        }

        try {
            $cartData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            return Cart::fromArray($cartData);
        } catch (\JsonException) {
            return null;
        }
    }

    public function save(Cart $cart): void
    {
        $key = $this->getKey($cart->getUserId());
        $data = json_encode($cart->toArray(), JSON_THROW_ON_ERROR);

        $this->redis->set($key, $data, self::TTL);
    }

    public function delete(UserId $userId): void
    {
        $key = $this->getKey($userId);
        $this->redis->delete($key);
    }

    private function getKey(UserId $userId): string
    {
        return self::KEY_PREFIX . $userId->toString();
    }
}

