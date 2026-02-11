<?php

declare(strict_types=1);

namespace Nocart\Checkout\Infrastructure\Persistence;

use Nocart\Checkout\Domain\Aggregate\CheckoutSession;
use Nocart\Checkout\Domain\Repository\CheckoutSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisCheckoutSessionRepository implements CheckoutSessionRepositoryInterface
{
    private const KEY_PREFIX = 'checkout:session:';
    private const TTL = 86400; // 24 hours

    public function __construct(
        private RedisClientInterface $redis
    ) {
    }

    public function findBySessionId(string $sessionId): ?CheckoutSession
    {
        $data = $this->redis->get(self::KEY_PREFIX . $sessionId);

        if ($data === null) {
            return null;
        }

        $decoded = json_decode($data, true);
        if ($decoded === null) {
            return null;
        }

        return CheckoutSession::fromArray($decoded);
    }

    public function save(CheckoutSession $session): void
    {
        $this->redis->set(
            self::KEY_PREFIX . $session->getSessionId(),
            json_encode($session->toArray()),
            self::TTL
        );
    }

    public function delete(string $sessionId): void
    {
        $this->redis->delete(self::KEY_PREFIX . $sessionId);
    }
}
