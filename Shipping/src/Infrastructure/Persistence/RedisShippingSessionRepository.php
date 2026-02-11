<?php

declare(strict_types=1);

namespace Nocart\Shipping\Infrastructure\Persistence;

use Nocart\Shipping\Domain\Aggregate\ShippingSession;
use Nocart\Shipping\Domain\Repository\ShippingSessionRepositoryInterface;
use Nocart\Shipping\Domain\ValueObject\Address;
use Nocart\Shipping\Domain\ValueObject\Money;
use Nocart\Shipping\Domain\ValueObject\ShippingMethod;
use Nocart\SharedKernel\Infrastructure\Persistence\RedisClientInterface;

final readonly class RedisShippingSessionRepository implements ShippingSessionRepositoryInterface
{
    private const TTL = 86400; // 24 hours

    public function __construct(
        private RedisClientInterface $redis
    ) {
    }

    public function findBySessionId(string $sessionId): ?ShippingSession
    {
        $key = "shipping:session:{$sessionId}";
        $data = $this->redis->get($key);

        if (!$data) {
            return null;
        }

        $array = json_decode($data, true);
        $session = new ShippingSession($array['session_id'], $array['user_id']);

        if (!empty($array['selected_method'])) {
            $methodData = $array['selected_method'];
            $method = new ShippingMethod(
                id: $methodData['id'],
                name: $methodData['name'],
                price: new Money($methodData['price_cents']),
                deliveryDays: $methodData['delivery_days'],
                carrier: $methodData['carrier']
            );
            $session->selectMethod($method);
        }

        if (!empty($array['address'])) {
            $addrData = $array['address'];
            $address = new Address(
                street: $addrData['street'],
                city: $addrData['city'],
                postalCode: $addrData['postal_code'],
                country: $addrData['country'],
                phoneNumber: $addrData['phone_number'] ?? null
            );
            $session->setAddress($address);
        }

        if (!empty($array['delivery_date'])) {
            $session->setDeliveryDate(
                $array['delivery_date'],
                $array['is_express'] ?? false
            );
        }

        return $session;
    }

    public function save(ShippingSession $session): void
    {
        $key = "shipping:session:{$session->getSessionId()}";
        $data = json_encode($session->toArray());
        $this->redis->set($key, $data, self::TTL);
    }
}
