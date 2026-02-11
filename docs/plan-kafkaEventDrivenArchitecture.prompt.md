# Plan: Komunikacja między mikroserwisami wyłącznie przez Kafkę

## Cel

Transformacja architektury z synchronicznej (REST) na asynchroniczną (Kafka Event-Driven) gdzie każdy serwis buduje lokalne view danych zamiast odpytywać inne aplikacje REST API. Zmiana `install.sh` na budowanie przez Docker Composer.

## Analiza obecnego stanu

### Problem 1: install.sh zakłada lokalnego composera
Obecny `install.sh` wykonuje `composer install` w każdym folderze serwisu zakładając, że composer jest zainstalowany lokalnie na hoście. To jest błędne podejście - composer powinien być uruchamiany wewnątrz kontenerów Docker.

### Problem 2: Synchroniczna komunikacja REST [90% DONE - 2026-02-11]

**Zaimplementowano:**
- Value Objects: CartView, ShippingView, PaymentView, PromotionView, ServicesView
- Repository Interfaces + Redis implementations (5x)
- EventHandlers budują lokalne view z eventów
- Query/Command handlers czytają z view zamiast HTTP
- ✅ DI bindings w services.yaml
- ✅ Usunięto HTTP Clients (folder + config)
- ✅ Czyszczenie composer.json, framework.yaml, services.yaml, docker-compose.yml
- ✅ Naprawiono volume mapping dla SharedKernel w docker-compose.yml

**TODO:**
- Rozszerzyć eventy o pełne dane (wymaga zmian w source serwisach: Cart, Shipping, Payment, Promotion, Services)
- Dodać CheckoutEventHandler w Cart Service (konsumowanie OrderCompleted)
- composer update w Checkout (usunięcie symfony/http-client z vendor/)

Checkout Service używa HTTP clientów do synchronicznego odpytywania innych serwisów:
- `CartServiceClient` - GET /cart
- `ShippingServiceClient` - GET /shipping/session, GET /shipping/available
- `PaymentServiceClient` - GET /payment/status
- `PromotionServiceClient` - GET /promotions/session, GET /promotions/available
- `ServicesServiceClient` - GET /services/session

To tworzy:
- Tight coupling między serwisami
- Synchroniczne zależności (jeśli Cart Service nie działa, Checkout nie może pobrać danych)
- Dodatkowe opóźnienie (N wywołań HTTP)
- Problemy ze skalowaniem

### Istniejąca infrastruktura Kafka
Projekt już ma zaimplementowaną podstawową infrastrukturę Kafka:
- `KafkaEventConsumer` w SharedKernel
- `EventHandlers` w Checkout (CartEventHandler, ShippingEventHandler, etc.)
- Topics: cart-events, shipping-events, payment-events, promotion-events, services-events, checkout-events
- EventHandlers już aktualizują CheckoutSession aggregate

## Docelowa architektura

### Event-Driven Communication Pattern

Każdy serwis:
1. **Publikuje pełne snapshoty danych** w eventach (nie tylko ID)
2. **Konsumuje eventy** z innych serwisów które są dla niego istotne
3. **Buduje lokalne view** w Redis/pamięci
4. **Czyta tylko z lokalnego view** - zero REST callów między serwisami

### Przykład flow (dodanie produktu do koszyka):

**Obecnie (REST):**
```
1. User → Cart Service: POST /cart/items
2. Cart Service → Kafka: CartItemAdded {item_id, quantity}
3. User → Checkout Service: GET /checkout/summary
4. Checkout → Cart Service: GET /cart (REST)
5. Checkout → Shipping Service: GET /shipping/session (REST)
6. Checkout → Promotion Service: GET /promotions/session (REST)
7. Checkout → Response to User
```

**Docelowo (Kafka):**
```
1. User → Cart Service: POST /cart/items
2. Cart Service → Kafka: CartItemAdded {item_id, quantity, price, cart_total, items: [...]}
3. Checkout Consumer: handle CartItemAdded → update local Redis view
4. User → Checkout Service: GET /checkout/summary
5. Checkout → Read from local Redis view (cart, shipping, promotions)
6. Checkout → Response to User
```

### Struktura lokalnych view w Checkout

```
Redis keys:
- checkout:cart_view:{session_id} → pełne dane koszyka
- checkout:shipping_view:{session_id} → wybrana metoda, adres, koszty
- checkout:payment_view:{session_id} → wybrana metoda, status
- checkout:promotion_view:{session_id} → zastosowane promocje, rabaty
- checkout:services_view:{session_id} → wybrane usługi, koszty
```

## Steps

### 1. Naprawić install.sh - budowanie przez Docker

**Problem:** Obecny `install.sh` zakłada lokalnego composera

**Rozwiązanie:** 
- Zmodyfikować `docker/php/Dockerfile` aby dodać stage instalacji dependencies
- Zmienić `install.sh` aby używał `docker-compose build` i `docker-compose run` do instalacji zależności
- Opcjonalnie: multi-stage build gdzie dependencies są instalowane w build stage

**Pliki do modyfikacji:**
- `install.sh` - zastąpić `composer install` na docker commands
- `docker/php/Dockerfile` - dodać etap instalacji dependencies
- `docker-compose.yml` - upewnić się że volumes są poprawnie zmapowane

**Nowy flow w install.sh:**
```bash
# Zamiast: cd Cart && composer install
# Wykonaj: docker-compose run --rm cart-php composer install
```

### 2. Rozszerzyć eventy Kafka o pełne dane

**Obecny stan:** Eventy zawierają minimalne dane (ID, wartości numeryczne)

**Docelowy stan:** Eventy zawierają pełny snapshot potrzebny do zbudowania view

**Zmiany w każdym serwisie:**

#### Cart Service
Event: `CartItemAdded`
```php
// Obecnie:
['session_id', 'user_id', 'item_id', 'quantity', 'cart_total']

// Docelowo:
[
    'session_id',
    'user_id',
    'cart_total' => ['amount' => 5999, 'currency' => 'PLN'],
    'items_count' => 3,
    'items' => [
        [
            'id' => 'uuid-1',
            'offer_id' => 123,
            'type' => 'product',
            'name' => 'Laptop Dell XPS',
            'quantity' => 1,
            'unit_price' => ['amount' => 5999, 'currency' => 'PLN'],
            'total_price' => ['amount' => 5999, 'currency' => 'PLN'],
            'parent_item_id' => null,
        ],
        // ... children items (warranty, accessories)
    ],
]
```

Event: `CartSnapshot` (nowy event typu "full state")
- Publikowany okresowo lub na żądanie
- Zawiera kompletny stan koszyka
- Umożliwia rebuild view bez replay wszystkich eventów

#### Shipping Service
Event: `ShippingMethodSelected`
```php
// Docelowo:
[
    'session_id',
    'method' => [
        'id' => 'courier-dpd',
        'name' => 'Kurier DPD',
        'price' => ['amount' => 1599, 'currency' => 'PLN'],
        'estimated_days' => 1,
    ],
    'address' => [
        'street' => 'ul. Przykładowa 123',
        'city' => 'Warszawa',
        'postal_code' => '00-001',
        'country' => 'PL',
    ],
    'delivery_date' => '2026-02-12',
    'is_express' => false,
    'total_shipping_cost' => ['amount' => 1599, 'currency' => 'PLN'],
]
```

#### Promotion Service
Event: `PromotionApplied`
```php
// Docelowo:
[
    'session_id',
    'applied_promotions' => [
        [
            'id' => 'promo-2x50',
            'name' => '2 za pół ceny',
            'discount_amount' => ['amount' => 299950, 'currency' => 'PLN'],
            'applicable_items' => ['uuid-1', 'uuid-2'],
        ],
    ],
    'promo_codes' => [
        ['code' => 'WELCOME10', 'discount' => ['amount' => 50000, 'currency' => 'PLN']],
    ],
    'total_discount' => ['amount' => 349950, 'currency' => 'PLN'],
]
```

#### Services Service
Event: `ServicesSessionUpdated`
```php
// Docelowo:
[
    'session_id',
    'selected_services' => [
        [
            'id' => 'sms-notification',
            'name' => 'Powiadomienie SMS',
            'type' => 'standalone',
            'price' => ['amount' => 200, 'currency' => 'PLN'],
        ],
        [
            'id' => 'installation-fridge',
            'name' => 'Wniesienie i instalacja',
            'type' => 'item_service',
            'parent_item_id' => 'uuid-3',
            'price' => ['amount' => 9900, 'currency' => 'PLN'],
        ],
    ],
    'total_services_price' => ['amount' => 10100, 'currency' => 'PLN'],
]
```

#### Payment Service
Event: `PaymentMethodSelected`
```php
// Docelowo:
[
    'session_id',
    'method' => [
        'id' => 'blik',
        'name' => 'BLIK',
        'provider' => 'przelewy24',
    ],
    'status' => 'selected', // selected, initialized, pending, succeeded, failed
    'transaction_id' => null,
]
```

**Pliki do modyfikacji:**
- `Cart/src/Domain/Event/*.php` - rozszerzyć payload eventów
- `Cart/src/Application/Command/*Handler.php` - publikować pełniejsze eventy
- Analogicznie dla pozostałych serwisów

### 3. Wprowadzić Local View Pattern w Checkout

**Utworzyć nowe klasy Value Objects dla view:**

```
Checkout/src/Domain/ValueObject/
├── CartView.php
├── ShippingView.php
├── PaymentView.php
├── PromotionView.php
└── ServicesView.php
```

**Utworzyć repozytoria dla view:**

```
Checkout/src/Domain/Repository/
├── CartViewRepositoryInterface.php
├── ShippingViewRepositoryInterface.php
├── PaymentViewRepositoryInterface.php
├── PromotionViewRepositoryInterface.php
└── ServicesViewRepositoryInterface.php

Checkout/src/Infrastructure/Persistence/
├── RedisCartViewRepository.php
├── RedisShippingViewRepository.php
├── RedisPaymentViewRepository.php
├── RedisPromotionViewRepository.php
└── RedisServicesViewRepository.php
```

**Przykładowa implementacja CartView:**

```php
final readonly class CartView
{
    /** @param CartItem[] $items */
    public function __construct(
        public SessionId $sessionId,
        public Money $total,
        public int $itemsCount,
        public array $items,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromEvent(array $event): self
    {
        return new self(
            sessionId: SessionId::fromString($event['session_id']),
            total: Money::fromArray($event['cart_total']),
            itemsCount: $event['items_count'],
            items: array_map(
                fn($item) => CartItem::fromArray($item),
                $event['items'] ?? []
            ),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function toArray(): array { /* ... */ }
    public static function fromArray(array $data): self { /* ... */ }
}
```

### 4. Zaktualizować EventHandlers aby budowały lokalne view

**Zmodyfikować istniejące EventHandlers:**

```php
// Checkout/src/Application/EventHandler/CartEventHandler.php

final readonly class CartEventHandler
{
    public function __construct(
        private CheckoutSessionRepositoryInterface $sessionRepository,
        private CartViewRepositoryInterface $cartViewRepository, // NOWE
        private EventPublisherInterface $eventPublisher,
    ) {}

    public function handleCartItemAdded(array $event): void
    {
        // 1. Zaktualizuj CartView (pełny snapshot koszyka)
        $cartView = CartView::fromEvent($event);
        $this->cartViewRepository->save($cartView);

        // 2. Zaktualizuj CheckoutSession (tylko totals)
        $sessionId = SessionId::fromString($event['session_id']);
        $session = $this->sessionRepository->findBySessionId($sessionId);
        
        if ($session === null) {
            $userId = UserId::fromString($event['user_id']);
            $session = CheckoutSession::create($sessionId, $userId);
        }

        $cartTotal = Money::fromArray($event['cart_total']);
        $session->updateCartTotal($cartTotal, $event['correlation_id'] ?? null);

        $this->sessionRepository->save($session);

        // 3. Publikuj CheckoutTotalsUpdated
        foreach ($session->pullDomainEvents() as $domainEvent) {
            $this->eventPublisher->publish(KafkaTopics::CHECKOUT_EVENTS, $domainEvent);
        }
    }

    public function handleCartItemRemoved(array $event): void { /* analogicznie */ }
    public function handleCartItemQuantityChanged(array $event): void { /* analogicznie */ }
    public function handleCartCleared(array $event): void
    {
        $sessionId = SessionId::fromString($event['session_id']);
        $this->cartViewRepository->delete($sessionId);
        $this->sessionRepository->delete($sessionId);
    }
}
```

**Analogicznie dla:**
- `ShippingEventHandler` → aktualizuje `ShippingView`
- `PromotionEventHandler` → aktualizuje `PromotionView`
- `ServicesEventHandler` → aktualizuje `ServicesView`
- `PaymentEventHandler` → aktualizuje `PaymentView`

### 5. Przepisać Query/Command Handlers aby czytały z lokalnych view

**Zmodyfikować GetCheckoutSummaryHandler:**

```php
// Checkout/src/Application/Query/GetCheckoutSummaryHandler.php

#[AsMessageHandler]
final readonly class GetCheckoutSummaryHandler
{
    public function __construct(
        private CheckoutSessionRepositoryInterface $repository,
        // USUNĄĆ wszystkie HTTP clienty
        // DODAĆ repozytoria view
        private CartViewRepositoryInterface $cartViewRepository,
        private ShippingViewRepositoryInterface $shippingViewRepository,
        private PaymentViewRepositoryInterface $paymentViewRepository,
        private PromotionViewRepositoryInterface $promotionViewRepository,
        private ServicesViewRepositoryInterface $servicesViewRepository,
    ) {}

    public function __invoke(GetCheckoutSummaryQuery $query): array
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->repository->findBySessionId($sessionId);

        if ($session === null) {
            $session = CheckoutSession::create($sessionId, UserId::fromString($query->userId));
        }

        // Czytaj z lokalnych Redis view zamiast HTTP callów
        $cartView = $this->cartViewRepository->findBySessionId($sessionId);
        $shippingView = $this->shippingViewRepository->findBySessionId($sessionId);
        $paymentView = $this->paymentViewRepository->findBySessionId($sessionId);
        $promotionView = $this->promotionViewRepository->findBySessionId($sessionId);
        $servicesView = $this->servicesViewRepository->findBySessionId($sessionId);

        return [
            'session' => $session->toArray(),
            'cart' => $cartView?->toArray() ?? [],
            'shipping' => $shippingView?->toArray() ?? [],
            'payment' => $paymentView?->toArray() ?? [],
            'promotions' => $promotionView?->toArray() ?? [],
            'services' => $servicesView?->toArray() ?? [],
        ];
    }
}
```

**Zmodyfikować RecalculateTotalsHandler:**

```php
// Checkout/src/Application/Command/RecalculateTotalsHandler.php

#[AsMessageHandler]
final readonly class RecalculateTotalsHandler
{
    public function __construct(
        private CheckoutSessionRepositoryInterface $repository,
        private EventPublisherInterface $eventPublisher,
        // USUNĄĆ HTTP clienty, DODAĆ view repositories
        private CartViewRepositoryInterface $cartViewRepository,
        private ShippingViewRepositoryInterface $shippingViewRepository,
        private PromotionViewRepositoryInterface $promotionViewRepository,
        private ServicesViewRepositoryInterface $servicesViewRepository,
    ) {}

    public function __invoke(RecalculateTotalsCommand $command): void
    {
        $sessionId = SessionId::fromString($command->sessionId);
        $userId = UserId::fromString($command->userId);
        $session = $this->repository->findBySessionId($sessionId);

        if ($session === null) {
            $session = CheckoutSession::create($sessionId, $userId);
        }

        // Czytaj z lokalnych view
        $cartView = $this->cartViewRepository->findBySessionId($sessionId);
        if ($cartView !== null) {
            $session->updateCartTotal($cartView->total, $command->correlationId);
        }

        $shippingView = $this->shippingViewRepository->findBySessionId($sessionId);
        if ($shippingView !== null) {
            $session->updateShippingTotal(
                $shippingView->totalCost,
                $shippingView->selectedMethod?->id,
                $command->correlationId
            );
        }

        $promotionView = $this->promotionViewRepository->findBySessionId($sessionId);
        if ($promotionView !== null) {
            $session->updatePromotionDiscount($promotionView->totalDiscount, $command->correlationId);
        }

        $servicesView = $this->servicesViewRepository->findBySessionId($sessionId);
        if ($servicesView !== null) {
            $session->updateServicesTotal($servicesView->totalPrice, $command->correlationId);
        }

        $this->repository->save($session);

        foreach ($session->pullDomainEvents() as $event) {
            $this->eventPublisher->publish(KafkaTopics::CHECKOUT_EVENTS, $event);
        }
    }
}
```

**Zmodyfikować CompletePaymentHandler** - usunąć `CartServiceClient::clearCart()`:

```php
// Zamiast wywoływać REST API aby wyczyścić koszyk:
// $this->cartClient->clearCart($userId, $sessionId);

// Opublikować event który Cart Service skonsumuje:
$this->eventPublisher->publish(
    KafkaTopics::CHECKOUT_EVENTS,
    new CartClearRequested($sessionId, $userId, $orderId)
);
```

### 6. Usunąć zależności HTTP między serwisami

**A. Usunąć folder Infrastructure/Client:**
```
Checkout/src/Infrastructure/Client/
├── CartServiceClient.php         ❌ DELETE
├── ShippingServiceClient.php     ❌ DELETE
├── PaymentServiceClient.php      ❌ DELETE
├── PromotionServiceClient.php    ❌ DELETE
└── ServicesServiceClient.php     ❌ DELETE
```

**B. Zmodyfikować composer.json:**
```json
// Checkout/composer.json
{
    "require": {
        "php": ">=8.4",
        "ext-json": "*",
        "ext-rdkafka": "*",
        "nocart/shared-kernel": "@dev",
        "symfony/framework-bundle": "^7.2",
        "symfony/runtime": "^7.2",
        "symfony/serializer": "^7.2",
        "symfony/property-access": "^7.2",
        "symfony/uid": "^7.2",
        // "symfony/http-client": "^7.2",  ❌ USUNĄĆ
        "predis/predis": "^2.2"
    }
}
```

**C. Zmodyfikować docker-compose.yml:**

Usunąć zmienne środowiskowe z URL-ami innych serwisów:
```yaml
checkout-php:
  environment:
    - APP_ENV=dev
    - REDIS_HOST=redis
    - KAFKA_BROKERS=redpanda:9092
    - SERVICE_NAME=checkout
    # ❌ USUNĄĆ poniższe linie:
    # - CART_SERVICE_URL=http://cart-nginx
    # - SHIPPING_SERVICE_URL=http://shipping-nginx
    # - PAYMENT_SERVICE_URL=http://payment-nginx
    # - PROMOTION_SERVICE_URL=http://promotion-nginx
    # - SERVICES_SERVICE_URL=http://services-nginx
  depends_on:
    redis:
      condition: service_healthy
    redpanda:
      condition: service_healthy
    # ❌ USUNĄĆ dependencies na inne nginx services:
    # cart-nginx:
    #   condition: service_started
    # shipping-nginx:
    #   condition: service_started
    # ...
```

**D. Usunąć konfigurację HTTP clientów:**
```yaml
# Checkout/config/services.yaml
# ❌ USUNĄĆ sekcję:
# Nocart\Checkout\Infrastructure\Client\CartServiceClient:
#     arguments:
#         $httpClient: '@cart.http_client'
# 
# framework:
#     http_client:
#         scoped_clients:
#             cart.http_client:
#                 base_uri: '%env(CART_SERVICE_URL)%'
```

### 7. Dodać Kafka consumers jako długo-działające procesy

**Opcja A: Symfony Messenger + Supervisor**

Utworzyć Messenger transport dla Kafka:
```yaml
# Checkout/config/packages/messenger.yaml
framework:
    messenger:
        transports:
            kafka_cart_events:
                dsn: 'kafka://redpanda:9092'
                options:
                    topic:
                        name: 'cart-events'
                    kafka_conf:
                        group.id: 'checkout-consumer'
                        auto.offset.reset: 'earliest'
            kafka_shipping_events:
                dsn: 'kafka://redpanda:9092'
                options:
                    topic:
                        name: 'shipping-events'
                    kafka_conf:
                        group.id: 'checkout-consumer'
            # ... inne topics

        routing:
            'Nocart\SharedKernel\Domain\Event\Cart\CartItemAdded': kafka_cart_events
            'Nocart\SharedKernel\Domain\Event\Shipping\ShippingMethodSelected': kafka_shipping_events
            # ...
```

Supervisor config:
```ini
# docker/supervisord/checkout.conf
[program:checkout_cart_consumer]
command=php /var/www/html/bin/console messenger:consume kafka_cart_events -vv
autostart=true
autorestart=true
user=appuser
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr

[program:checkout_shipping_consumer]
command=php /var/www/html/bin/console messenger:consume kafka_shipping_events -vv
autostart=true
autorestart=true
user=appuser
stdout_logfile=/dev/stdout
stderr_logfile=/dev/stderr

# ... inne consumers
```

**Opcja B: Dedykowane command + Supervisor**

Utworzyć command który uruchamia KafkaEventConsumer:
```php
// Checkout/src/Application/Command/ConsumeCartEventsCommand.php

#[AsCommand(name: 'app:consume:cart-events')]
final class ConsumeCartEventsCommand extends Command
{
    public function __construct(
        private KafkaEventConsumer $consumer,
        private CartEventHandler $handler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting cart events consumer...');

        $this->consumer->subscribe('cart-events', 'checkout-consumer');
        
        $this->consumer->consume('cart-events', function (array $event) use ($output) {
            $output->writeln(sprintf('Processing event: %s', $event['event_type']));
            
            match ($event['event_type']) {
                'cart.item_added' => $this->handler->handleCartItemAdded($event),
                'cart.item_removed' => $this->handler->handleCartItemRemoved($event),
                'cart.item_quantity_changed' => $this->handler->handleCartItemQuantityChanged($event),
                'cart.cleared' => $this->handler->handleCartCleared($event),
                default => $output->writeln('Unknown event type'),
            };
        });

        return Command::SUCCESS;
    }
}
```

**Modyfikacja docker-compose.yml:**

Dodać supervisor do kontenerów:
```yaml
checkout-php:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  command: ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
  volumes:
    - ./docker/supervisord/checkout.conf:/etc/supervisor/conf.d/checkout.conf
```

### 8. Obsługa Cart clearing przez event

Cart Service musi konsumować eventy z checkout-events:

```php
// Cart/src/Application/EventHandler/CheckoutEventHandler.php

final readonly class CheckoutEventHandler
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
    ) {}

    public function handleOrderCompleted(array $event): void
    {
        $userId = UserId::fromString($event['user_id']);
        $cart = $this->cartRepository->findByUserId($userId);

        if ($cart !== null) {
            $this->cartRepository->delete($userId);
        }
    }
}
```

Consumer w Cart Service:
```ini
# docker/supervisord/cart.conf
[program:cart_checkout_consumer]
command=php /var/www/html/bin/console app:consume:checkout-events -vv
autostart=true
autorestart=true
user=appuser
```

### 9. Opcjonalnie: Snapshot events dla rebuild

Dodać endpoint w każdym serwisie do publikacji pełnego snapshota:

```php
// Cart/src/Ports/Http/CartSnapshotController.php

#[Route('/internal/snapshot/{sessionId}', methods: ['POST'])]
final class CartSnapshotController
{
    public function __construct(
        private CartRepositoryInterface $repository,
        private EventPublisherInterface $publisher,
    ) {}

    public function __invoke(string $sessionId): JsonResponse
    {
        $cart = $this->repository->findBySessionId(SessionId::fromString($sessionId));
        
        if ($cart === null) {
            return new JsonResponse(['error' => 'Cart not found'], 404);
        }

        $snapshot = new CartSnapshot(
            sessionId: $cart->getSessionId()->toString(),
            userId: $cart->getUserId()->toString(),
            items: $cart->getItems(),
            total: $cart->getTotal(),
        );

        $this->publisher->publish(KafkaTopics::CART_EVENTS, $snapshot);

        return new JsonResponse(['status' => 'published']);
    }
}
```

Użycie: Gdy Checkout nie ma view dla danej sesji, może wywołać snapshot endpoint wszystkich serwisów (lub czekać na kolejny event).

## Korzyści tej architektury

1. **Loose coupling** - serwisy nie znają się nawzajem, komunikacja tylko przez Kafkę
2. **Niezależne skalowanie** - każdy serwis może być skalowany niezależnie
3. **Resilience** - jeśli Cart Service nie działa, Checkout nadal może zwrócić dane (z cache)
4. **Performance** - zero synchronicznych REST callów, odczyt z lokalnego Redis (ns vs ms)
5. **Eventual consistency** - akceptowalne w kontekście e-commerce (koszyk nie musi być real-time)
6. **Event sourcing ready** - łatwo dodać event store i replay eventów

## Trade-offs i wyzwania

### 1. Eventual Consistency
**Problem:** Dane mogą być nieaktualne (opóźnienie 10-100ms)

**Mitigacja:**
- W większości przypadków akceptowalne dla koszyka
- Dla krytycznych operacji (płatność) - walidacja w czasie rzeczywistym przed finalizacją
- Można dodać korelację event → view update → potwierdzenie

### 2. Initial Load Problem
**Problem:** Co jeśli Checkout odpytuje view które jeszcze nie istnieje?

**Rozwiązania:**
- **A) Zwrócić puste dane** - najprostsza opcja, frontend pokaże pusty koszyk
- **B) Snapshot on demand** - wywołać endpoint snapshot w innych serwisach
- **C) Graceful degradation** - pokazać cached dane z komunikatem "odświeżanie..."
- **D) Pre-populate przez replay** - przy starcie Checkout skonsumować ostatnie N eventów

**Rekomendacja:** Opcja A + D (puste dane + replay ostatnich eventów z Kafka przy starcie)

### 3. Data Consistency
**Problem:** Jak zapewnić że view jest spójny z source of truth?

**Rozwiązania:**
- **Checksums** - każdy event zawiera checksum danych, view porównuje
- **Version numbers** - każdy event ma version, view odrzuca stare wersje
- **Periodic reconciliation** - job który porównuje view z source i naprawia różnice
- **Idempotent handlers** - EventHandlers muszą być idempotentne (ten sam event = ten sam wynik)

### 4. Schema Evolution
**Problem:** Jak zmieniać strukturę eventów bez breaking changes?

**Rozwiązania:**
- **Backward compatibility** - nowe pola zawsze opcjonalne, stare pola deprecated
- **Event versioning** - `CartItemAdded_v2`, consumery obsługują obie wersje
- **Schema Registry** - Avro/Protobuf schema registry (np. Confluent Schema Registry)

**Rekomendacja:** Backward compatibility + event versioning dla major changes

### 5. Testing
**Problem:** Jak testować event-driven flows?

**Rozwiązania:**
- **Unit tests** - testować EventHandlers z mock eventami
- **Integration tests** - testować producer → consumer flow z test Kafka
- **Contract tests** - testować schema eventów (JSON Schema validation)
- **E2E tests** - testować pełen flow z prawdziwą Kafką (już istnieje w `tests/`)

### 6. Debugging
**Problem:** Trudniejszy debugging rozproszonego flow

**Rozwiązania:**
- **Correlation ID** - każdy event zawiera correlation_id, logować wszędzie
- **Tracing** - OpenTelemetry distributed tracing
- **Kafka UI** - Redpanda Console już jest w docker-compose (port 8080)
- **Event logging** - logować każdy consumed event z payload

## Kolejność implementacji

### Faza 1: Przygotowanie (1-2 dni)
1. ✅ Naprawić `install.sh` - budowanie przez Docker
2. ✅ **DONE 2026-02-11** Dodać value objects dla view w Checkout (CartView, ShippingView, etc.)
3. ✅ **DONE 2026-02-11** Dodać repository interfaces i Redis implementations
4. ⏸️ Dodać unit tests dla nowych klas

### Faza 2: Cart Service → Checkout (2-3 dni)
5. ⏸️ Rozszerzyć eventy Cart Service o pełne dane
6. ✅ **DONE 2026-02-11** Zmodyfikować CartEventHandler w Checkout aby budował CartView
7. ✅ **DONE 2026-02-11** Zmodyfikować GetCheckoutSummaryHandler - czytać z CartView zamiast HTTP
8. ✅ **DONE 2026-02-11** Zmodyfikować RecalculateTotalsHandler - czytać z CartView zamiast HTTP
9. ⏸️ Testy integracyjne
10. ⏸️ Usunąć CartServiceClient (czeka na testy + konfigurację services.yaml)

### Faza 3: Shipping Service → Checkout (1-2 dni)
11. ⏸️ Rozszerzyć eventy Shipping Service
12. ✅ **DONE 2026-02-11** Zmodyfikować ShippingEventHandler → ShippingView
13. ✅ **DONE 2026-02-11** Zaktualizować Query/Command handlers
14. ⏸️ Usunąć ShippingServiceClient

### Faza 4: Payment/Promotion/Services → Checkout (2-3 dni)
15. ✅ **DONE 2026-02-11** Analogicznie dla Payment Service (PaymentEventHandler → PaymentView)
16. ✅ **DONE 2026-02-11** Analogicznie dla Promotion Service (PromotionEventHandler → PromotionView)
17. ✅ **DONE 2026-02-11** Analogicznie dla Services Service (ServicesEventHandler → ServicesView)
18. ⏸️ Usunąć wszystkie pozostałe HTTP clienty

### Faza 5: Consumers i cleanup (1-2 dni)
19. ⏸️ Dodać Kafka consumers jako długo-działające procesy (Supervisor)
20. ⏸️ Dodać CheckoutEventHandler w Cart Service (obsługa OrderCompleted)
21. ⏸️ Wyczyścić docker-compose.yml (URLs, dependencies)
22. ⏸️ Wyczyścić composer.json (symfony/http-client)
23. ⏸️ Wyczyścić services.yaml (HTTP clients config)

### Faza 6: Testing i dokumentacja (2-3 dni)
24. ⏸️ Testy E2E pełnego flow (wykorzystać istniejące w `tests/`)
25. ⏸️ Load testing - porównać performance przed/po
26. ⏸️ Zaktualizować dokumentację (kafka-event-flow.md)
27. ⏸️ Dodać przykłady payloadów eventów do docs
28. ⏸️ Monitoring i alerting dla consumers

---

**STATUS: Fazy 1-4 wykonane w 80%. Zobacz: `docs/problem2-rest-communication-DONE.md`**

## Pliki do utworzenia/modyfikacji

### Nowe pliki (utworzyć):

```
Checkout/src/Domain/ValueObject/
├── CartView.php
├── ShippingView.php
├── PaymentView.php
├── PromotionView.php
├── ServicesView.php
├── CartItem.php
├── ShippingMethod.php
├── PaymentMethod.php
└── SelectedService.php

Checkout/src/Domain/Repository/
├── CartViewRepositoryInterface.php
├── ShippingViewRepositoryInterface.php
├── PaymentViewRepositoryInterface.php
├── PromotionViewRepositoryInterface.php
└── ServicesViewRepositoryInterface.php

Checkout/src/Infrastructure/Persistence/
├── RedisCartViewRepository.php
├── RedisShippingViewRepository.php
├── RedisPaymentViewRepository.php
├── RedisPromotionViewRepository.php
└── RedisServicesViewRepository.php

Checkout/src/Application/Command/
├── ConsumeCartEventsCommand.php
├── ConsumeShippingEventsCommand.php
├── ConsumePaymentEventsCommand.php
├── ConsumePromotionEventsCommand.php
└── ConsumeServicesEventsCommand.php

Cart/src/Application/EventHandler/
└── CheckoutEventHandler.php

Cart/src/Application/Command/
└── ConsumeCheckoutEventsCommand.php

SharedKernel/src/Domain/Event/Checkout/
└── OrderCompleted.php (jeśli nie istnieje)

docker/supervisord/
└── checkout.conf (rozszerzyć o wszystkie consumers)

docs/
└── event-payloads.md (przykłady wszystkich eventów)
```

### Pliki do modyfikacji:

```
install.sh
docker/php/Dockerfile
docker-compose.yml

Checkout/composer.json
Checkout/config/services.yaml
Checkout/config/packages/messenger.yaml (jeśli używamy Symfony Messenger)

Checkout/src/Application/EventHandler/CartEventHandler.php
Checkout/src/Application/EventHandler/ShippingEventHandler.php
Checkout/src/Application/EventHandler/PaymentEventHandler.php
Checkout/src/Application/EventHandler/PromotionEventHandler.php
Checkout/src/Application/EventHandler/ServicesEventHandler.php

Checkout/src/Application/Query/GetCheckoutSummaryHandler.php
Checkout/src/Application/Command/RecalculateTotalsHandler.php
Checkout/src/Application/Command/CompletePaymentHandler.php

Cart/src/Domain/Event/*.php
Cart/src/Application/Command/*Handler.php
Shipping/src/Domain/Event/*.php
Shipping/src/Application/Command/*Handler.php
Payment/src/Domain/Event/*.php
Payment/src/Application/Command/*Handler.php
Promotion/src/Domain/Event/*.php
Promotion/src/Application/Command/*Handler.php
Services/src/Domain/Event/*.php
Services/src/Application/Command/*Handler.php

docs/kafka-event-flow.md
```

### Pliki do usunięcia:

```
Checkout/src/Infrastructure/Client/CartServiceClient.php
Checkout/src/Infrastructure/Client/ShippingServiceClient.php
Checkout/src/Infrastructure/Client/PaymentServiceClient.php
Checkout/src/Infrastructure/Client/PromotionServiceClient.php
Checkout/src/Infrastructure/Client/ServicesServiceClient.php
```

## Monitoring i Observability

### Metryki do śledzenia:

1. **Event lag** - opóźnienie między publikacją a konsumpcją eventu
2. **Consumer lag** - ile eventów czeka w kolejce (Kafka consumer lag)
3. **View freshness** - jak stare są dane w view (timestamp)
4. **Event processing time** - ile czasu zajmuje przetworzenie eventu
5. **Failed events** - ile eventów nie zostało przetworzonych (DLQ)

### Narzędzia:

- **Redpanda Console** (już jest) - monitoring topics, consumer groups, lag
- **Prometheus + Grafana** - metryki aplikacji
- **Jaeger/Zipkin** - distributed tracing
- **ELK Stack** - centralne logowanie

### Health checks:

Dodać endpoint w każdym serwisie:
```php
#[Route('/health/consumers', methods: ['GET'])]
public function consumersHealth(): JsonResponse
{
    return new JsonResponse([
        'cart_consumer' => [
            'status' => 'running',
            'last_event_at' => '2026-02-11T10:30:45Z',
            'processed_today' => 1234,
            'lag' => 0,
        ],
        // ... inne consumers
    ]);
}
```

## Rollback Strategy

W przypadku problemów po wdrożeniu:

### Plan A: Feature Flag
Dodać feature flag który przełącza między REST a Kafka:
```php
if ($this->featureFlags->isEnabled('event_driven_checkout')) {
    $cartData = $this->cartViewRepository->findBySessionId($sessionId);
} else {
    $cartData = $this->cartClient->getCart($userId, $sessionId);
}
```

### Plan B: Blue-Green Deployment
- Mieć dwie wersje Checkout: stara (REST) i nowa (Kafka)
- Routing przez load balancer z możliwością szybkiego przełączenia

### Plan C: Gradual Rollout
- Zacząć od jednego serwisu (np. Cart)
- Monitorować przez tydzień
- Stopniowo dodawać kolejne (Shipping, Payment, etc.)

## Podsumowanie

Ta transformacja zmieni architekturę z **synchronicznej request-response** na **asynchroniczną event-driven**, gdzie:

- ✅ **Zero REST calls** między mikroserwisami
- ✅ **Lokalne view** budowane przez Kafka events
- ✅ **Loose coupling** - serwisy niezależne
- ✅ **Lepsza performance** - odczyt z local Redis (sub-ms) zamiast HTTP (10-50ms)
- ✅ **Lepsza resilience** - awaria jednego serwisu nie blokuje innych
- ✅ **Skalowanie** - każdy serwis skaluje się niezależnie
- ✅ **Event history** - Kafka jako audit log wszystkich zmian

Trade-offs:
- ⚠️ **Eventual consistency** - dane mogą być opóźnione (10-100ms)
- ⚠️ **Większa złożoność** - więcej komponentów do zarządzania
- ⚠️ **Debugging** - trudniejszy w rozproszonym systemie
- ⚠️ **Nauka** - zespół musi rozumieć event-driven patterns

Rekomendowane jest **stopniowe wdrażanie** (faza po fazie) z **monitoringiem** i możliwością **rollback** w przypadku problemów.
