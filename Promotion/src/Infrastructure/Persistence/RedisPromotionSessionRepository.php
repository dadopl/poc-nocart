<?php

declare(strict_types=1);

namespace Nocart\Promotion\Infrastructure\Persistence;

use Nocart\Promotion\Domain\Aggregate\PromotionSession;
use Nocart\Promotion\Domain\Repository\PromotionSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisPromotionSessionRepository implements PromotionSessionRepositoryInterface
{
    private const TTL = 86400; // 24 hours

    public function __construct(
        private RedisClientInterface $redis
    ) {
    }

    public function findBySessionId(string $sessionId): ?PromotionSession
    {
        $key = "promotion:session:{$sessionId}";
        $data = $this->redis->get($key);

        if (!$data) {
            return null;
        }

        $array = json_decode($data, true);
        $session = new PromotionSession($array['session_id'], $array['user_id']);

        // Restore applied codes (which also adds to applied_promotions)
        foreach ($array['applied_codes'] ?? [] as $codeData) {
            $session->applyPromoCode(
                $codeData['code'],
                $codeData['promotion_id'],
                $codeData['name'],
                $codeData['discount_cents']
            );
        }

        // Restore any promotions that were applied directly (not via code)
        foreach ($array['applied_promotions'] ?? [] as $promotion) {
            if (!$session->hasPromotion($promotion['id'])) {
                $session->applyPromotion(
                    $promotion['id'],
                    $promotion['name'],
                    $promotion['discount_cents']
                );
            }
        }

        return $session;
    }

    public function save(PromotionSession $session): void
    {
        $key = "promotion:session:{$session->getSessionId()}";
        $data = json_encode($session->toArray());
        $this->redis->set($key, $data, self::TTL);
    }
}
