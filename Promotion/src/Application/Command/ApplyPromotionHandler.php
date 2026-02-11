<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\Command;

use Nocart\Promotion\Domain\Aggregate\PromotionSession;
use Nocart\Promotion\Domain\Repository\PromotionRepositoryInterface;
use Nocart\Promotion\Domain\Repository\PromotionSessionRepositoryInterface;
use Nocart\SharedKernel\Infrastructure\Messaging\EventPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplyPromotionHandler
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
        private PromotionSessionRepositoryInterface $sessionRepository,
        private EventPublisherInterface $eventPublisher
    ) {
    }

    public function __invoke(ApplyPromotionCommand $command): array
    {
        $promotion = $this->promotionRepository->findById($command->promotionId);

        if (!$promotion) {
            throw new \InvalidArgumentException("Promotion '{$command->promotionId}' not found");
        }

        if (!$promotion->isApplicable($command->cartTotalCents, $command->itemQuantity)) {
            throw new \InvalidArgumentException("Promotion '{$command->promotionId}' is not applicable");
        }

        $discountCents = $promotion->calculateDiscount($command->cartTotalCents);

        $session = $this->sessionRepository->findBySessionId($command->sessionId)
            ?? new PromotionSession($command->sessionId, $command->userId);

        $session->applyPromotion($promotion->getId(), $promotion->getName(), $discountCents);
        $this->sessionRepository->save($session);

        // Publish event
        $this->eventPublisher->publish('promotion-events', [
            'event_type' => 'PromotionApplied',
            'session_id' => $command->sessionId,
            'user_id' => $command->userId,
            'promotion_id' => $promotion->getId(),
            'discount_cents' => $discountCents,
            'correlation_id' => $command->correlationId,
            'occurred_at' => date('c'),
        ]);

        return [
            'message' => 'Promotion applied successfully',
            'discount_cents' => $discountCents,
            'promotion_name' => $promotion->getName(),
        ];
    }
}
