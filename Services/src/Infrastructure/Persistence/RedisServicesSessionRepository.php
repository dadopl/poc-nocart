<?php

declare(strict_types=1);

namespace Nocart\Services\Infrastructure\Persistence;

use Nocart\Services\Domain\Aggregate\ServicesSession;
use Nocart\Services\Domain\Repository\ServicesSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisServicesSessionRepository implements ServicesSessionRepositoryInterface
{
    private const TTL = 86400; // 24 hours

    public function __construct(
        private RedisClientInterface $redis
    ) {
    }

    public function findBySessionId(string $sessionId): ?ServicesSession
    {
        $key = "services:session:{$sessionId}";
        $data = $this->redis->get($key);

        if (!$data) {
            return null;
        }

        $array = json_decode($data, true);
        $session = new ServicesSession($array['session_id'], $array['user_id']);

        foreach ($array['selected_services'] ?? [] as $service) {
            $session->selectService(
                $service['id'],
                $service['name'],
                $service['price_cents']
            );
        }

        return $session;
    }

    public function save(ServicesSession $session): void
    {
        $key = "services:session:{$session->getSessionId()}";
        $data = json_encode($session->toArray());
        $this->redis->set($key, $data, self::TTL);
    }
}
