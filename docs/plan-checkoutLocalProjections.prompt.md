# Plan: Lokalne projekcje w Checkout Service zamiast sięgania do obcych danych Redis

## Problem

Checkout Service aktualnie sięga bezpośrednio do kluczy Redis innych mikroserwisów:
- `cart:session:{sessionId}` - dane Cart Service
- `shipping:session:{sessionId}` - dane Shipping Service
- `promotion:session:{sessionId}` - dane Promotion Service
- `services:session:{sessionId}` - dane Services Service
- `payment:session:{sessionId}` - dane Payment Service

To łamie zasadę izolacji mikroserwisów i tworzy ukryte zależności.

## Rozwiązanie

Checkout Service powinien budować **własne lokalne projekcje** na podstawie eventów Kafka, zachowując pełną izolację mikroserwisów i spójność danych.

---

## Kroki implementacji

### Krok 1: Utworzyć nowy agregat `CheckoutOrder`

**Lokalizacja:** `Checkout/src/Domain/Aggregate/CheckoutOrder.php`

Agregat zawierający lokalne snapshoty danych z innych serwisów:

```php
final class CheckoutOrder
{
    private string $sessionId;
    private string $userId;
    private string $status;
    
    // Lokalne projekcje (snapshoty) budowane z eventów
    private array $cartSnapshot = ['items' => [], 'total_cents' => 0];
    private array $shippingSnapshot = ['method' => null, 'address' => null, 'cost' => 0];
    private array $promotionSnapshot = ['applied' => [], 'codes' => [], 'total_discount' => 0];
    private array $servicesSnapshot = ['selected' => [], 'total_cost' => 0];
    private array $paymentSnapshot = ['method' => null, 'status' => null, 'transaction_id' => null];
    
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    
    // Metody apply*() do aktualizacji snapshotów na podstawie eventów
    public function applyCartItemAdded(array $payload): void;
    public function applyCartItemRemoved(array $payload): void;
    public function applyCartItemQuantityChanged(array $payload): void;
    public function applyCartCleared(array $payload): void;
    
    public function applyShippingMethodSelected(array $payload): void;
    public function applyShippingAddressProvided(array $payload): void;
    public function applyShippingDeliveryDateSelected(array $payload): void;
    
    public function applyPromotionApplied(array $payload): void;
    public function applyPromotionRemoved(array $payload): void;
    public function applyPromoCodeApplied(array $payload): void;
    
    public function applyServicesSelected(array $payload): void;
    
    public function applyPaymentMethodSelected(array $payload): void;
    public function applyPaymentInitialized(array $payload): void;
    public function applyPaymentSucceeded(array $payload): void;
    public function applyPaymentFailed(array $payload): void;
    
    // Kalkulacja totali
    public function calculateGrandTotal(): int;
    public function getTotals(): array;
}
```

---

### Krok 2: Utworzyć Event Handlery

**Lokalizacja:** `Checkout/src/Application/EventHandler/`

#### 2.1 `CartEventHandler.php`

Obsługuje eventy z topiku `cart-events`:
- `cart.item_added`
- `cart.item_removed`
- `cart.item_quantity_changed`
- `cart.cleared`

```php
final class CartEventHandler
{
    public function __construct(
        private CheckoutOrderRepositoryInterface $repository
    ) {}
    
    public function handle(array $event): void
    {
        $sessionId = $event['payload']['cart_id'] ?? $event['aggregate_id'];
        $order = $this->repository->findBySessionId($sessionId) 
            ?? CheckoutOrder::create($sessionId);
        
        match($event['event_name']) {
            'cart.item_added' => $order->applyCartItemAdded($event['payload']),
            'cart.item_removed' => $order->applyCartItemRemoved($event['payload']),
            'cart.item_quantity_changed' => $order->applyCartItemQuantityChanged($event['payload']),
            'cart.cleared' => $order->applyCartCleared($event['payload']),
        };
        
        $this->repository->save($order);
    }
}
```

#### 2.2 `ShippingEventHandler.php`

Obsługuje eventy z topiku `shipping-events`:
- `shipping.method_selected`
- `shipping.address_provided`
- `shipping.delivery_date_selected`

#### 2.3 `PromotionEventHandler.php`

Obsługuje eventy z topiku `promotion-events`:
- `promotion.applied`
- `promotion.removed`
- `promotion.code_applied`

#### 2.4 `ServicesEventHandler.php`

Obsługuje eventy z topiku `services-events`:
- `services.availability_calculated`
- `services.selected`

#### 2.5 `PaymentEventHandler.php`

Obsługuje eventy z topiku `payment-events`:
- `payment.method_selected`
- `payment.initialized`
- `payment.succeeded`
- `payment.failed`

---

### Krok 3: Utworzyć repozytorium `CheckoutOrderRepository`

**Lokalizacja:** 
- Interface: `Checkout/src/Domain/Repository/CheckoutOrderRepositoryInterface.php`
- Implementacja: `Checkout/src/Infrastructure/Persistence/RedisCheckoutOrderRepository.php`

```php
interface CheckoutOrderRepositoryInterface
{
    public function save(CheckoutOrder $order): void;
    public function findBySessionId(string $sessionId): ?CheckoutOrder;
    public function delete(string $sessionId): void;
}
```

**Klucz Redis:** `checkout:order:{sessionId}` (własny namespace Checkout Service)

---

### Krok 4: Utworzyć komendę konsumera eventów

**Lokalizacja:** `Checkout/src/Ports/Console/CheckoutConsumeEventsCommand.php`

```php
#[AsCommand(name: 'checkout:consume-events')]
final class CheckoutConsumeEventsCommand extends Command
{
    public function __construct(
        private KafkaEventConsumer $consumer,
        private CartEventHandler $cartHandler,
        private ShippingEventHandler $shippingHandler,
        private PromotionEventHandler $promotionHandler,
        private ServicesEventHandler $servicesHandler,
        private PaymentEventHandler $paymentHandler,
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Subskrybuj wszystkie potrzebne topiki
        $topics = [
            KafkaTopics::CART_EVENTS,
            KafkaTopics::SHIPPING_EVENTS,
            KafkaTopics::PROMOTION_EVENTS,
            KafkaTopics::SERVICES_EVENTS,
            KafkaTopics::PAYMENT_EVENTS,
        ];
        
        foreach ($topics as $topic) {
            $this->consumer->subscribe($topic, 'checkout-consumer');
        }
        
        $this->consumer->consume(function (array $event) {
            $this->dispatch($event);
        });
        
        return Command::SUCCESS;
    }
    
    private function dispatch(array $event): void
    {
        $eventName = $event['event_name'];
        
        match(true) {
            str_starts_with($eventName, 'cart.') => $this->cartHandler->handle($event),
            str_starts_with($eventName, 'shipping.') => $this->shippingHandler->handle($event),
            str_starts_with($eventName, 'promotion.') => $this->promotionHandler->handle($event),
            str_starts_with($eventName, 'services.') => $this->servicesHandler->handle($event),
            str_starts_with($eventName, 'payment.') => $this->paymentHandler->handle($event),
            default => null, // Ignoruj nieznane eventy
        };
    }
}
```

---

### Krok 5: Zmodyfikować `GetCheckoutTotalsHandler`

**Lokalizacja:** `Checkout/src/Application/Query/GetCheckoutTotalsHandler.php`

**Przed (odczyt z cudzych danych):**
```php
public function __invoke(GetCheckoutTotalsQuery $query): array
{
    $cartView = $this->cartViewRepository->getCartView($query->sessionId);
    $shippingView = $this->shippingViewRepository->getShippingView($query->sessionId);
    $promotionView = $this->promotionViewRepository->getPromotionView($query->sessionId);
    $servicesView = $this->servicesViewRepository->getServicesView($query->sessionId);
    // ...
}
```

**Po (odczyt z lokalnej projekcji):**
```php
public function __invoke(GetCheckoutTotalsQuery $query): array
{
    $order = $this->checkoutOrderRepository->findBySessionId($query->sessionId);
    
    if ($order === null) {
        return $this->emptyTotals();
    }
    
    return $order->getTotals();
}
```

---

### Krok 6: Usunąć stare repozytoria

**Pliki do usunięcia:**

Interfejsy:
- `Checkout/src/Domain/Repository/CartViewRepositoryInterface.php`
- `Checkout/src/Domain/Repository/ShippingViewRepositoryInterface.php`
- `Checkout/src/Domain/Repository/PromotionViewRepositoryInterface.php`
- `Checkout/src/Domain/Repository/ServicesViewRepositoryInterface.php`
- `Checkout/src/Domain/Repository/PaymentViewRepositoryInterface.php`

Implementacje:
- `Checkout/src/Infrastructure/Persistence/RedisCartViewRepository.php`
- `Checkout/src/Infrastructure/Persistence/RedisShippingViewRepository.php`
- `Checkout/src/Infrastructure/Persistence/RedisPromotionViewRepository.php`
- `Checkout/src/Infrastructure/Persistence/RedisServicesViewRepository.php`
- `Checkout/src/Infrastructure/Persistence/RedisPaymentViewRepository.php`

---

## Struktura plików po zmianach

```
Checkout/src/
├── Application/
│   ├── Command/
│   ├── EventHandler/
│   │   ├── CartEventHandler.php          # NOWY
│   │   ├── ShippingEventHandler.php      # NOWY
│   │   ├── PromotionEventHandler.php     # NOWY
│   │   ├── ServicesEventHandler.php      # NOWY
│   │   └── PaymentEventHandler.php       # NOWY
│   └── Query/
│       ├── GetCheckoutTotalsHandler.php  # ZMODYFIKOWANY
│       └── GetCheckoutTotalsQuery.php
├── Domain/
│   ├── Aggregate/
│   │   ├── CheckoutSession.php
│   │   └── CheckoutOrder.php             # NOWY
│   └── Repository/
│       ├── CheckoutSessionRepositoryInterface.php
│       └── CheckoutOrderRepositoryInterface.php  # NOWY
├── Infrastructure/
│   ├── Messaging/
│   └── Persistence/
│       ├── RedisCheckoutSessionRepository.php
│       └── RedisCheckoutOrderRepository.php      # NOWY
├── Ports/
│   ├── Console/
│   │   └── CheckoutConsumeEventsCommand.php      # NOWY
│   └── Http/
│       ├── CheckoutController.php
│       └── HealthController.php
└── Kernel.php
```

---

## Dodatkowe rozważania

### 1. Eventual Consistency

Dane w Checkout mogą być chwilowo nieaktualne (milisekundy do sekund). To standardowe podejście w Event-Driven Architecture. 

**Mitygacja:** 
- Używaj `correlation_id` do śledzenia przepływu
- Rozważ optimistic locking przy finalizacji zamówienia

### 2. Replay eventów (Cold Start)

Po restarcie serwisu lub utracie danych Redis, stan można odtworzyć z historii Kafka.

**Opcje:**
- Kafka log compaction - zachowuje ostatni stan dla każdego klucza
- Dedykowany endpoint do rebuildu projekcji
- Snapshot + replay od ostatniego offsetu

### 3. Idempotentność

Handlery muszą być idempotentne - ten sam event przetworzony wielokrotnie daje ten sam wynik.

**Implementacja:**
```php
final class CheckoutOrder
{
    private array $processedEventIds = [];
    
    public function wasEventProcessed(string $eventId): bool
    {
        return in_array($eventId, $this->processedEventIds, true);
    }
    
    public function markEventProcessed(string $eventId): void
    {
        $this->processedEventIds[] = $eventId;
        // Zachowaj tylko ostatnie N eventów
        $this->processedEventIds = array_slice($this->processedEventIds, -100);
    }
}
```

### 4. Obsługa błędów

- Dead Letter Queue dla eventów, które nie mogą być przetworzone
- Retry z exponential backoff
- Alerty przy przekroczeniu lag'u konsumera

---

## Przepływ danych po zmianach

```
┌─────────────┐     cart-events      ┌─────────────────────────────────────┐
│   Cart      │─────────────────────▶│         Checkout Service            │
│   Service   │                      │                                     │
└─────────────┘                      │  ┌─────────────────────────────┐    │
                                     │  │   CartEventHandler          │    │
┌─────────────┐     shipping-events  │  │   ShippingEventHandler      │    │
│  Shipping   │─────────────────────▶│  │   PromotionEventHandler     │    │
│   Service   │                      │  │   ServicesEventHandler      │    │
└─────────────┘                      │  │   PaymentEventHandler       │    │
                                     │  └──────────────┬──────────────┘    │
┌─────────────┐     promotion-events │                 │                   │
│  Promotion  │─────────────────────▶│                 ▼                   │
│   Service   │                      │  ┌─────────────────────────────┐    │
└─────────────┘                      │  │      CheckoutOrder          │    │
                                     │  │   (lokalna projekcja)       │    │
┌─────────────┐     services-events  │  │                             │    │
│  Services   │─────────────────────▶│  │  - cartSnapshot             │    │
│   Service   │                      │  │  - shippingSnapshot         │    │
└─────────────┘                      │  │  - promotionSnapshot        │    │
                                     │  │  - servicesSnapshot         │    │
┌─────────────┐     payment-events   │  │  - paymentSnapshot          │    │
│  Payment    │─────────────────────▶│  └──────────────┬──────────────┘    │
│   Service   │                      │                 │                   │
└─────────────┘                      │                 ▼                   │
                                     │  ┌─────────────────────────────┐    │
                                     │  │  Redis (własny namespace)   │    │
                                     │  │  checkout:order:{sessionId} │    │
                                     │  └─────────────────────────────┘    │
                                     └─────────────────────────────────────┘
```

---

## Kolejność implementacji

1. **Faza 1:** Utworzyć `CheckoutOrder` i `CheckoutOrderRepository`
2. **Faza 2:** Utworzyć Event Handlery
3. **Faza 3:** Utworzyć `CheckoutConsumeEventsCommand`
4. **Faza 4:** Zmodyfikować `GetCheckoutTotalsHandler` (dual-read: stary + nowy)
5. **Faza 5:** Testy integracyjne
6. **Faza 6:** Usunąć stare repozytoria
7. **Faza 7:** Aktualizacja dokumentacji
