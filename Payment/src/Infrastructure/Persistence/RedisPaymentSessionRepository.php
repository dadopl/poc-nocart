<?php

declare(strict_types=1);

namespace Nocart\Payment\Infrastructure\Persistence;

use Nocart\Payment\Domain\Aggregate\PaymentSession;
use Nocart\Payment\Domain\Repository\PaymentSessionRepositoryInterface;
use Nocart\Payment\Domain\ValueObject\Money;
use Nocart\Payment\Domain\ValueObject\PaymentMethod;
use Nocart\Payment\Domain\ValueObject\PaymentStatus;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisPaymentSessionRepository implements PaymentSessionRepositoryInterface
{
    private const TTL = 86400; // 24 hours

    public function __construct(
        private RedisClientInterface $redis
    ) {
    }

    public function findBySessionId(string $sessionId): ?PaymentSession
    {
        $key = "payment:session:{$sessionId}";
        $data = $this->redis->get($key);

        if (!$data) {
            return null;
        }

        $array = json_decode($data, true);
        $session = new PaymentSession($array['session_id'], $array['user_id']);

        if (!empty($array['selected_method_data'])) {
            $methodData = $array['selected_method_data'];
            $method = new PaymentMethod(
                id: $methodData['id'],
                name: $methodData['name'],
                type: $methodData['type'],
                fee: new Money($methodData['fee_cents'])
            );
            $session->selectMethod($method);
        }

        // Restore status
        if (!empty($array['status'])) {
            $status = PaymentStatus::from($array['status']);
            if ($status === PaymentStatus::PROCESSING) {
                $session->processPayment($array['amount'] ?? 0);
            } elseif (($status === PaymentStatus::COMPLETED || $status === PaymentStatus::SUCCEEDED) && !empty($array['transaction_id'])) {
                $session->processPayment($array['amount'] ?? 0);
                $session->confirmPayment($array['transaction_id']);
            } elseif ($status === PaymentStatus::FAILED) {
                $session->processPayment($array['amount'] ?? 0);
                $session->failPayment();
            }
        }

        return $session;
    }

    public function save(PaymentSession $session): void
    {
        $key = "payment:session:{$session->getSessionId()}";
        $data = json_encode($session->toArray());
        $this->redis->set($key, $data, self::TTL);
    }
}
