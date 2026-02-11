<?php

declare(strict_types=1);

namespace Nocart\Checkout\Infrastructure\Persistence;

use Nocart\Checkout\Domain\Aggregate\CheckoutOrder;
use Nocart\Checkout\Domain\Repository\CheckoutOrderRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisCheckoutOrderRepository implements CheckoutOrderRepositoryInterface
{
    private const KEY_PREFIX = 'checkout:order:';
    private const TTL = 86400; // 24 hours

    public function __construct(
        private RedisClientInterface $redis
    ) {
    }

    public function findBySessionId(string $sessionId): ?CheckoutOrder
    {
        $data = $this->redis->get(self::KEY_PREFIX . $sessionId);

        if ($data === null) {
            return null;
        }

        try {
            return CheckoutOrder::fromJson($data);
        } catch (\JsonException) {
            return null;
        }
    }

    public function save(CheckoutOrder $order): void
    {
        $this->redis->set(
            self::KEY_PREFIX . $order->getSessionId(),
            $order->toJson(),
            self::TTL
        );
    }

    public function delete(string $sessionId): void
    {
        $this->redis->delete(self::KEY_PREFIX . $sessionId);
    }
}
