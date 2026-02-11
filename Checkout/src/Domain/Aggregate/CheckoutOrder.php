<?php

declare(strict_types=1);

namespace Nocart\Checkout\Domain\Aggregate;

/**
 * Agregat CheckoutOrder - lokalna projekcja danych z innych mikroserwisów
 * budowana na podstawie eventów Kafka.
 *
 * Przechowuje snapshoty stanu: koszyka, dostawy, promocji, usług i płatności.
 */
final class CheckoutOrder
{
    private string $sessionId;
    private ?string $userId = null;
    private string $status = 'pending';

    /** @var array{items: array<string, array{item_id: string, item_type: string, offer_id: int, quantity: int, price_amount: int, price_currency: string, parent_item_id: string|null}>, total_cents: int, currency: string} */
    private array $cartSnapshot = [
        'items' => [],
        'total_cents' => 0,
        'currency' => 'PLN',
    ];

    /** @var array{method_id: string|null, method_name: string|null, cost: int, currency: string, address: array|null, delivery_date: string|null, is_express: bool, express_fee: int} */
    private array $shippingSnapshot = [
        'method_id' => null,
        'method_name' => null,
        'cost' => 0,
        'currency' => 'PLN',
        'address' => null,
        'delivery_date' => null,
        'is_express' => false,
        'express_fee' => 0,
    ];

    /** @var array{applied: array<string, array{promotion_id: string, promotion_name: string, discount_amount: int, discount_currency: string}>, codes: array<string, array{code: string, discount_amount: int, discount_currency: string}>, total_discount: int, currency: string} */
    private array $promotionSnapshot = [
        'applied' => [],
        'codes' => [],
        'total_discount' => 0,
        'currency' => 'PLN',
    ];

    /** @var array{selected: array<string, array{service_id: string, service_name: string, price: int, currency: string}>, total_cost: int, currency: string} */
    private array $servicesSnapshot = [
        'selected' => [],
        'total_cost' => 0,
        'currency' => 'PLN',
    ];

    /** @var array{method_id: string|null, method_name: string|null, status: string|null, transaction_id: string|null, order_id: string|null, amount: int, currency: string, failure_reason: string|null} */
    private array $paymentSnapshot = [
        'method_id' => null,
        'method_name' => null,
        'status' => null,
        'transaction_id' => null,
        'order_id' => null,
        'amount' => 0,
        'currency' => 'PLN',
        'failure_reason' => null,
    ];

    /** @var array<string> Lista przetworzonych event_id dla idempotentności */
    private array $processedEventIds = [];

    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(string $sessionId, ?string $userId = null): self
    {
        $order = new self($sessionId);
        $order->userId = $userId;

        return $order;
    }

    // ========================
    // Idempotentność
    // ========================

    public function wasEventProcessed(string $eventId): bool
    {
        return in_array($eventId, $this->processedEventIds, true);
    }

    public function markEventProcessed(string $eventId): void
    {
        $this->processedEventIds[] = $eventId;
        // Zachowaj tylko ostatnie 100 eventów aby nie rozrastać się w nieskończoność
        if (count($this->processedEventIds) > 100) {
            $this->processedEventIds = array_slice($this->processedEventIds, -100);
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ========================
    // Cart Events
    // ========================

    /**
     * @param array{cart_id: string, item_id: string, item_type: string, offer_id: int, quantity: int, price_amount: int, price_currency: string, parent_item_id: string|null} $payload
     */
    public function applyCartItemAdded(array $payload): void
    {
        $itemId = $payload['item_id'];

        $this->cartSnapshot['items'][$itemId] = [
            'item_id' => $itemId,
            'item_type' => $payload['item_type'],
            'offer_id' => $payload['offer_id'],
            'quantity' => $payload['quantity'],
            'price_amount' => $payload['price_amount'],
            'price_currency' => $payload['price_currency'],
            'parent_item_id' => $payload['parent_item_id'] ?? null,
        ];

        $this->recalculateCartTotal();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{cart_id: string, item_id: string, item_type: string} $payload
     */
    public function applyCartItemRemoved(array $payload): void
    {
        $itemId = $payload['item_id'];

        if (isset($this->cartSnapshot['items'][$itemId])) {
            unset($this->cartSnapshot['items'][$itemId]);
        }

        // Usuń również elementy potomne (np. gwarancje przypisane do produktu)
        foreach ($this->cartSnapshot['items'] as $id => $item) {
            if (($item['parent_item_id'] ?? null) === $itemId) {
                unset($this->cartSnapshot['items'][$id]);
            }
        }

        $this->recalculateCartTotal();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{cart_id: string, item_id: string, old_quantity: int, new_quantity: int} $payload
     */
    public function applyCartItemQuantityChanged(array $payload): void
    {
        $itemId = $payload['item_id'];

        if (isset($this->cartSnapshot['items'][$itemId])) {
            $this->cartSnapshot['items'][$itemId]['quantity'] = $payload['new_quantity'];
        }

        $this->recalculateCartTotal();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{cart_id: string} $payload
     */
    public function applyCartCleared(array $payload): void
    {
        $this->cartSnapshot['items'] = [];
        $this->cartSnapshot['total_cents'] = 0;
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function recalculateCartTotal(): void
    {
        $total = 0;
        foreach ($this->cartSnapshot['items'] as $item) {
            $total += $item['price_amount'] * $item['quantity'];
        }
        $this->cartSnapshot['total_cents'] = $total;
    }

    // ========================
    // Shipping Events
    // ========================

    /**
     * @param array{session_id: string, method_id: string, method_name: string, price_amount: int, price_currency: string} $payload
     */
    public function applyShippingMethodSelected(array $payload): void
    {
        $this->shippingSnapshot['method_id'] = $payload['method_id'];
        $this->shippingSnapshot['method_name'] = $payload['method_name'];
        $this->shippingSnapshot['cost'] = $payload['price_amount'];
        $this->shippingSnapshot['currency'] = $payload['price_currency'];
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, street: string, city: string, postal_code: string, country: string} $payload
     */
    public function applyShippingAddressProvided(array $payload): void
    {
        $this->shippingSnapshot['address'] = [
            'street' => $payload['street'],
            'city' => $payload['city'],
            'postal_code' => $payload['postal_code'],
            'country' => $payload['country'],
        ];
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, delivery_date: string, is_express: bool, express_fee_amount: int} $payload
     */
    public function applyShippingDeliveryDateSelected(array $payload): void
    {
        $this->shippingSnapshot['delivery_date'] = $payload['delivery_date'];
        $this->shippingSnapshot['is_express'] = $payload['is_express'];
        $this->shippingSnapshot['express_fee'] = $payload['express_fee_amount'] ?? 0;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ========================
    // Promotion Events
    // ========================

    /**
     * @param array{session_id: string, promotion_id: string, promotion_name: string, discount_amount: int, discount_currency: string} $payload
     */
    public function applyPromotionApplied(array $payload): void
    {
        $promotionId = $payload['promotion_id'];

        $this->promotionSnapshot['applied'][$promotionId] = [
            'promotion_id' => $promotionId,
            'promotion_name' => $payload['promotion_name'],
            'discount_amount' => $payload['discount_amount'],
            'discount_currency' => $payload['discount_currency'],
        ];

        $this->recalculatePromotionDiscount();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, promotion_id: string} $payload
     */
    public function applyPromotionRemoved(array $payload): void
    {
        $promotionId = $payload['promotion_id'];

        if (isset($this->promotionSnapshot['applied'][$promotionId])) {
            unset($this->promotionSnapshot['applied'][$promotionId]);
        }

        $this->recalculatePromotionDiscount();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, code: string, discount_amount: int, discount_currency: string} $payload
     */
    public function applyPromoCodeApplied(array $payload): void
    {
        $code = $payload['code'];

        $this->promotionSnapshot['codes'][$code] = [
            'code' => $code,
            'discount_amount' => $payload['discount_amount'],
            'discount_currency' => $payload['discount_currency'],
        ];

        $this->recalculatePromotionDiscount();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function recalculatePromotionDiscount(): void
    {
        $total = 0;

        foreach ($this->promotionSnapshot['applied'] as $promotion) {
            $total += $promotion['discount_amount'];
        }

        foreach ($this->promotionSnapshot['codes'] as $code) {
            $total += $code['discount_amount'];
        }

        $this->promotionSnapshot['total_discount'] = $total;
    }

    // ========================
    // Services Events
    // ========================

    /**
     * @param array{session_id: string, service_id: string, service_name: string, price: int, currency: string} $payload
     */
    public function applyServiceSelected(array $payload): void
    {
        $serviceId = $payload['service_id'];

        $this->servicesSnapshot['selected'][$serviceId] = [
            'service_id' => $serviceId,
            'service_name' => $payload['service_name'],
            'price' => $payload['price'],
            'currency' => $payload['currency'],
        ];

        $this->recalculateServicesCost();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, service_id: string} $payload
     */
    public function applyServiceRemoved(array $payload): void
    {
        $serviceId = $payload['service_id'];

        if (isset($this->servicesSnapshot['selected'][$serviceId])) {
            unset($this->servicesSnapshot['selected'][$serviceId]);
        }

        $this->recalculateServicesCost();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, cart_hash: string, available_services: array, total_services_price: int} $payload
     */
    public function applyServicesAvailabilityCalculated(array $payload): void
    {
        // Ten event informuje o dostępnych usługach, nie zmienia wybranych
        // Można go użyć do walidacji lub logowania
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function recalculateServicesCost(): void
    {
        $total = 0;
        foreach ($this->servicesSnapshot['selected'] as $service) {
            $total += $service['price'];
        }
        $this->servicesSnapshot['total_cost'] = $total;
    }

    // ========================
    // Payment Events
    // ========================

    /**
     * @param array{session_id: string, method_id: string, method_name: string} $payload
     */
    public function applyPaymentMethodSelected(array $payload): void
    {
        $this->paymentSnapshot['method_id'] = $payload['method_id'];
        $this->paymentSnapshot['method_name'] = $payload['method_name'];
        $this->paymentSnapshot['status'] = 'selected';
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, transaction_id: string, method_id: string, amount_total: int, currency: string} $payload
     */
    public function applyPaymentInitialized(array $payload): void
    {
        $this->paymentSnapshot['transaction_id'] = $payload['transaction_id'];
        $this->paymentSnapshot['method_id'] = $payload['method_id'];
        $this->paymentSnapshot['amount'] = $payload['amount_total'];
        $this->paymentSnapshot['currency'] = $payload['currency'];
        $this->paymentSnapshot['status'] = 'initialized';
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, transaction_id: string, order_id: string} $payload
     */
    public function applyPaymentSucceeded(array $payload): void
    {
        $this->paymentSnapshot['transaction_id'] = $payload['transaction_id'];
        $this->paymentSnapshot['order_id'] = $payload['order_id'];
        $this->paymentSnapshot['status'] = 'succeeded';
        $this->paymentSnapshot['failure_reason'] = null;
        $this->status = 'paid';
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array{session_id: string, transaction_id: string, reason: string} $payload
     */
    public function applyPaymentFailed(array $payload): void
    {
        $this->paymentSnapshot['transaction_id'] = $payload['transaction_id'];
        $this->paymentSnapshot['status'] = 'failed';
        $this->paymentSnapshot['failure_reason'] = $payload['reason'];
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ========================
    // Kalkulacje
    // ========================

    public function calculateGrandTotal(): int
    {
        $subtotal = $this->cartSnapshot['total_cents'];
        $shippingCost = $this->shippingSnapshot['cost'] + $this->shippingSnapshot['express_fee'];
        $promotionDiscount = $this->promotionSnapshot['total_discount'];
        $servicesCost = $this->servicesSnapshot['total_cost'];

        $grandTotal = $subtotal + $shippingCost - $promotionDiscount + $servicesCost;

        return max(0, $grandTotal);
    }

    /**
     * @return array{subtotal: array{amount: int, currency: string}, shipping_cost: array{amount: int, currency: string}, promotion_discount: array{amount: int, currency: string}, services_cost: array{amount: int, currency: string}, grand_total: array{amount: int, currency: string}}
     */
    public function getTotals(): array
    {
        $shippingCost = $this->shippingSnapshot['cost'] + $this->shippingSnapshot['express_fee'];

        return [
            'subtotal' => [
                'amount' => $this->cartSnapshot['total_cents'],
                'currency' => $this->cartSnapshot['currency'],
            ],
            'shipping_cost' => [
                'amount' => $shippingCost,
                'currency' => $this->shippingSnapshot['currency'],
            ],
            'promotion_discount' => [
                'amount' => $this->promotionSnapshot['total_discount'],
                'currency' => $this->promotionSnapshot['currency'],
            ],
            'services_cost' => [
                'amount' => $this->servicesSnapshot['total_cost'],
                'currency' => $this->servicesSnapshot['currency'],
            ],
            'grand_total' => [
                'amount' => $this->calculateGrandTotal(),
                'currency' => 'PLN',
            ],
        ];
    }

    // ========================
    // Gettery
    // ========================

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCartSnapshot(): array
    {
        return $this->cartSnapshot;
    }

    public function getShippingSnapshot(): array
    {
        return $this->shippingSnapshot;
    }

    public function getPromotionSnapshot(): array
    {
        return $this->promotionSnapshot;
    }

    public function getServicesSnapshot(): array
    {
        return $this->servicesSnapshot;
    }

    public function getPaymentSnapshot(): array
    {
        return $this->paymentSnapshot;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ========================
    // Serializacja
    // ========================

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'status' => $this->status,
            'cart_snapshot' => $this->cartSnapshot,
            'shipping_snapshot' => $this->shippingSnapshot,
            'promotion_snapshot' => $this->promotionSnapshot,
            'services_snapshot' => $this->servicesSnapshot,
            'payment_snapshot' => $this->paymentSnapshot,
            'processed_event_ids' => $this->processedEventIds,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $order = new self($data['session_id']);
        $order->userId = $data['user_id'] ?? null;
        $order->status = $data['status'] ?? 'pending';
        $order->cartSnapshot = $data['cart_snapshot'] ?? $order->cartSnapshot;
        $order->shippingSnapshot = $data['shipping_snapshot'] ?? $order->shippingSnapshot;
        $order->promotionSnapshot = $data['promotion_snapshot'] ?? $order->promotionSnapshot;
        $order->servicesSnapshot = $data['services_snapshot'] ?? $order->servicesSnapshot;
        $order->paymentSnapshot = $data['payment_snapshot'] ?? $order->paymentSnapshot;
        $order->processedEventIds = $data['processed_event_ids'] ?? [];
        $order->createdAt = new \DateTimeImmutable($data['created_at'] ?? 'now');
        $order->updatedAt = new \DateTimeImmutable($data['updated_at'] ?? 'now');

        return $order;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }
}
