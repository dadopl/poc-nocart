<?php

declare(strict_types=1);

namespace Nocart\Payment\Application\Query;

use Nocart\Payment\Domain\Repository\PaymentSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPaymentStatusHandler
{
    public function __construct(
        private PaymentSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function __invoke(GetPaymentStatusQuery $query): array
    {
        $session = $this->sessionRepository->findBySessionId($query->sessionId);

        if (!$session) {
            return [
                'status' => 'pending',
                'selected_method' => null,
                'amount' => 0,
            ];
        }

        return $session->toArray();
    }
}
