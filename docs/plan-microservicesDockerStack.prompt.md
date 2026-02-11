# Plan: Docker Stack + Mikroserwisy POC dla Checkout Flow

Projekt obejmuje budowę dockerowego środowiska z Kafka, Redis oraz 6 mikroserwisów w Symfony 7.4 (PHP 8.4) z architekturą DDD, zakończony testem funkcjonalnym E2E pokrywającym cały flow.md.

## Architektura

```
┌─────────────────────────────────────────────────────────────────────┐
│                         DOCKER NETWORK                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │  Kafka   │  │ Zookeeper│  │  Redis   │  │ Redpanda │             │
│  │  :9092   │  │  :2181   │  │  :6379   │  │ Console  │             │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘             │
│                                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                           │
│  │  Cart    │  │ Shipping │  │ Payment  │                           │
│  │  :8001   │  │  :8002   │  │  :8003   │                           │
│  └──────────┘  └──────────┘  └──────────┘                           │
│                                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                           │
│  │ Checkout │  │Promotion │  │ Services │                           │
│  │  :8004   │  │  :8005   │  │  :8006   │                           │
│  └──────────┘  └──────────┘  └──────────┘                           │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Struktura DDD każdego mikroserwisu

```
{ServiceName}/
├── composer.json
├── config/
│   ├── packages/
│   │   ├── framework.yaml
│   │   ├── messenger.yaml
│   │   └── services.yaml
│   ├── routes.yaml
│   └── bundles.php
├── public/
│   └── index.php
├── src/
│   ├── Domain/
│   │   ├── Aggregate/
│   │   │   └── {AggregateName}.php
│   │   ├── Event/
│   │   │   └── {DomainEvent}.php
│   │   ├── ValueObject/
│   │   │   └── {ValueObject}.php
│   │   ├── Repository/
│   │   │   └── {RepositoryInterface}.php
│   │   └── Exception/
│   │       └── {DomainException}.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── {Command}.php
│   │   │   └── {CommandHandler}.php
│   │   ├── Query/
│   │   │   ├── {Query}.php
│   │   │   └── {QueryHandler}.php
│   │   └── EventHandler/
│   │       └── {ExternalEventHandler}.php
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── Redis{Repository}.php
│   │   ├── Messaging/
│   │   │   ├── KafkaEventPublisher.php
│   │   │   └── KafkaEventConsumer.php
│   │   └── Http/
│   │       └── {ExternalApiClient}.php
│   └── Ports/
│       └── Http/
│           └── {Controller}.php
├── tests/
│   ├── Unit/
│   │   └── Domain/
│   └── Functional/
└── Dockerfile
```

## Steps

### 1. Utworzenie infrastruktury Docker

**Pliki do utworzenia:**
- `docker-compose.yml` - główny plik orchestracji
- `docker/php/Dockerfile` - PHP 8.4-fpm z ext-redis, ext-rdkafka
- `docker/nginx/default.conf` - konfiguracja nginx dla API
- `.env.docker` - zmienne środowiskowe

**Kluczowe usługi:**
- Kafka (Redpanda) - lekka alternatywa, kompatybilna z Kafka API
- Redis 7.x - persistence dla agregatów
- 6x PHP-FPM + Nginx dla każdego mikroserwisu

### 2. Shared Kernel - wspólne kontrakty

**Plik:** `SharedKernel/`

Zawiera:
- `Event/` - interfejsy i bazowe klasy eventów
- `ValueObject/` - współdzielone Value Objects (Money, CartId, SessionId)
- `Message/` - kontrakty wiadomości Kafka

### 3. Cart Service

**Agregat:** `Cart`
- Properties: `CartId`, `UserId`, `items: CartItem[]`, `createdAt`, `updatedAt`
- Methods: `addItem()`, `removeItem()`, `changeQuantity()`, `getTotal()`

**Value Objects:**
- `CartId`, `CartItem`, `ItemType` (product, warranty, accessory, service_item, service_standalone, service_shipping)

**Domain Events:**
- `CartItemAdded`, `CartItemRemoved`, `CartItemQuantityChanged`, `CartCleared`

**Endpoints:**
- `POST /cart/items` - AddItemToCartCommand
- `DELETE /cart/items/{id}` - RemoveItemFromCartCommand
- `PATCH /cart/items/{id}` - ChangeItemQuantityCommand
- `GET /cart` - GetCartQuery

**Redis Keys:**
- `cart:{user_id}` - serializowany agregat Cart

### 4. Shipping Service

**Agregat:** `ShippingSession`
- Properties: `SessionId`, `selectedMethod`, `address`, `deliveryDate`, `addons`
- Methods: `selectMethod()`, `setAddress()`, `setDeliveryDate()`

**Value Objects:**
- `ShippingMethod`, `Address`, `DeliveryDate`

**Domain Events:**
- `ShippingMethodSelected`, `ShippingAddressProvided`, `DeliveryDateSelected`

**Endpoints:**
- `GET /shipping/available` - GetAvailableMethodsQuery
- `POST /shipping/select` - SelectShippingMethodCommand
- `POST /shipping/address` - SetShippingAddressCommand
- `GET /shipping/calendar` - GetDeliveryCalendarQuery
- `POST /shipping/set-date` - SetDeliveryDateCommand

**Redis Keys:**
- `shipping:session:{session_id}` - serializowany agregat
- `shipping:methods:cache:{cart_hash}` - cache dostępnych metod

### 5. Payment Service

**Agregat:** `PaymentSession`
- Properties: `SessionId`, `selectedMethod`, `transactionId`, `status`
- Methods: `selectMethod()`, `initializePayment()`, `confirmPayment()`, `failPayment()`

**Value Objects:**
- `PaymentMethod`, `TransactionId`, `PaymentStatus`

**Domain Events:**
- `PaymentMethodSelected`, `PaymentInitialized`, `PaymentSucceeded`, `PaymentFailed`

**Endpoints:**
- `GET /payment/available` - GetAvailableMethodsQuery
- `POST /payment/select` - SelectPaymentMethodCommand
- `POST /payment/initialize` - InitializePaymentCommand
- `POST /payment/confirm` - ConfirmPaymentCommand (symulacja BLIK)
- `GET /payment/status/{transaction_id}` - GetPaymentStatusQuery

**Redis Keys:**
- `payment:session:{session_id}` - serializowany agregat

### 6. Promotion Service

**Agregat:** `PromotionSession`
- Properties: `SessionId`, `appliedPromotions`, `appliedCodes`, `totalDiscount`
- Methods: `applyPromotion()`, `removePromotion()`, `applyCode()`

**Value Objects:**
- `Promotion`, `PromoCode`, `Discount`

**Domain Events:**
- `PromotionApplied`, `PromotionRemoved`, `PromoCodeApplied`

**Endpoints:**
- `GET /promotions/available` - GetAvailablePromotionsQuery
- `POST /promotions/apply` - ApplyPromotionCommand
- `DELETE /promotions/{id}` - RemovePromotionCommand
- `POST /promotions/apply-code` - ApplyPromoCodeCommand

**Redis Keys:**
- `promotions:session:{session_id}` - serializowany agregat
- `promotions:definitions` - statyczne definicje promocji

### 7. Services Service

**Agregat:** `ServicesSession`
- Properties: `SessionId`, `availableServices`
- Methods: `calculateAvailableServices()`

**Value Objects:**
- `Service`, `ServiceType` (service_item, service_standalone)

**Domain Events:**
- `ServiceAvailabilityCalculated`

**Endpoints:**
- `GET /services/available` - GetAvailableServicesQuery
- `GET /services/for-item/{item_id}` - GetServicesForItemQuery
- `GET /services/standalone` - GetStandaloneServicesQuery

**Redis Keys:**
- `services:available:{cart_hash}` - cache dostępnych usług
- `services:rules` - statyczne reguły

### 8. Checkout Service (Orchestrator)

**Agregat:** `CheckoutSession`
- Properties: `SessionId`, `UserId`, `totals`, `customerData`, `consents`, `status`
- Methods: `updateTotals()`, `setCustomerData()`, `setConsents()`, `finalize()`

**Value Objects:**
- `Totals` (cartTotal, promotionDiscount, shippingTotal, servicesTotal, grandTotal)
- `CustomerData`, `Consents`, `CheckoutStatus`

**Domain Events:**
- `TotalsUpdated`, `CustomerDataProvided`, `ConsentsGiven`, `OrderCreated`, `CheckoutCompleted`

**Event Handlers (Kafka Consumers):**
- `CartItemAddedHandler` - update totals
- `CartItemRemovedHandler` - update totals
- `ShippingMethodSelectedHandler` - update totals
- `PaymentMethodSelectedHandler` - update totals
- `PromotionAppliedHandler` - update totals
- `PaymentSucceededHandler` - complete checkout

**Endpoints:**
- `GET /checkout/summary` - GetCheckoutSummaryQuery
- `POST /checkout/customer-data` - SetCustomerDataCommand
- `POST /checkout/consents` - SetConsentsCommand
- `POST /checkout/finalize` - FinalizeCheckoutCommand
- `GET /checkout/totals` - GetTotalsQuery

**Redis Keys:**
- `checkout:session:{session_id}` - serializowany agregat
- `checkout:totals:{session_id}` - materialized view totals

### 9. Kafka Topics i Event Flow

**Topics:**
- `cart-events` - eventy z Cart Service
- `shipping-events` - eventy z Shipping Service
- `payment-events` - eventy z Payment Service
- `promotion-events` - eventy z Promotion Service
- `services-events` - eventy z Services Service
- `checkout-events` - eventy z Checkout Service

**Consumer Groups:**
- `shipping-consumer` - Shipping Service słucha cart-events
- `payment-consumer` - Payment Service słucha cart-events, shipping-events
- `promotion-consumer` - Promotion Service słucha cart-events
- `services-consumer` - Services Service słucha cart-events
- `checkout-consumer` - Checkout Service słucha wszystkich

### 10. Test Funkcjonalny E2E

**Plik:** `tests/Functional/CheckoutFlowTest.php`

**Scenariusz testowy (zgodny z flow.md):**

```php
#[Test]
public function testCompleteCheckoutFlow(): void
{
    // 1. Dodanie laptopa do koszyka
    $this->cartClient->addItem(offerId: 123, type: 'product', quantity: 1);
    $this->assertCartItemsCount(1);
    $this->assertCartTotal(5999.00);
    
    // 2. Dodanie gwarancji
    $this->cartClient->addItem(offerId: 456, type: 'warranty', parentItemId: $laptopId, quantity: 1);
    $this->assertCartTotal(6298.00);
    
    // 3. Dodanie torby
    $this->cartClient->addItem(offerId: 789, type: 'accessory', parentItemId: $laptopId, quantity: 1);
    $this->assertCartTotal(6447.00);
    
    // 4. Pobranie dostępnych promocji
    $promotions = $this->promotionClient->getAvailable(cartId: $cartId);
    $this->assertPromotionAvailable('promo-2x50');
    
    // 5. Aplikacja promocji (zwiększa ilość do 2)
    $this->promotionClient->apply(promotionId: 'promo-2x50', cartId: $cartId);
    $this->waitForKafkaSync();
    $this->assertCheckoutTotal(9894.50);
    
    // 6. Dodanie usługi SMS
    $this->cartClient->addItem(type: 'service_standalone', serviceId: 'sms-notif', price: 2.00);
    $this->assertCheckoutTotal(9896.50);
    
    // 7. Wybór metody dostawy
    $this->shippingClient->selectMethod(methodId: 'courier_dpd');
    $this->shippingClient->setAddress(postalCode: '00-001', city: 'Warszawa', street: 'Testowa 1');
    $this->waitForKafkaSync();
    $this->assertCheckoutTotal(9912.49);
    
    // 8. Dodanie express delivery
    $this->shippingClient->setDate(date: 'tomorrow', express: true);
    $this->assertCheckoutTotal(9942.48);
    
    // 9. Dodanie lodówki + wniesienie
    $this->cartClient->addItem(offerId: 999, type: 'product', quantity: 1);
    $this->cartClient->addItem(type: 'service_item', serviceId: 'carrying', parentItemId: $fridgeId, price: 99.00);
    $this->waitForKafkaSync();
    $this->assertCheckoutTotal(14040.48);
    
    // 10. Wybór płatności
    $this->paymentClient->selectMethod(methodId: 'blik');
    
    // 11. Dane klienta i zgody
    $this->checkoutClient->setCustomerData(email: 'jan@example.com', ...);
    $this->checkoutClient->setConsents(terms: true, privacy: true);
    
    // 12. Finalizacja
    $order = $this->checkoutClient->finalize();
    $this->assertOrderCreated($order);
    
    // 13. Płatność BLIK
    $this->paymentClient->initialize(orderId: $order->id);
    $this->paymentClient->confirmBlik(code: '123456');
    $this->waitForKafkaSync();
    
    // 14. Weryfikacja końcowa
    $this->assertPaymentStatus('PAID');
    $this->assertCartCleared();
}
```

## Uproszczenia POC

1. **Brak autentykacji** - user_id i session_id przekazywane w headerach
2. **Brak circuit breaker** - proste HTTP client
3. **Statyczne dane produktów** - hardcoded w serwisach
4. **Symulowany payment gateway** - bez prawdziwej integracji
5. **In-memory Kafka consumer** - dla testów funkcjonalnych
6. **Brak walidacji biznesowej** - minimalna logika

## Zależności Composer (każdy serwis)

```json
{
    "require": {
        "php": ">=8.4",
        "symfony/framework-bundle": "^7.4",
        "symfony/runtime": "^7.4",
        "symfony/serializer": "^7.4",
        "symfony/messenger": "^7.4",
        "symfony/http-client": "^7.4",
        "symfony/uid": "^7.4",
        "predis/predis": "^2.2",
        "koco/messenger-kafka": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "symfony/test-pack": "^1.0"
    }
}
```

## Kolejność implementacji

1. Docker infrastructure + SharedKernel
2. Cart Service (core, foundation)
3. Checkout Service (orchestrator, totals)
4. Promotion Service (discounts)
5. Services Service (additional services)
6. Shipping Service (delivery)
7. Payment Service (finalization)
8. Test funkcjonalny E2E

## Further Considerations

1. **Saga Pattern** - dla długotrwałych transakcji (finalizacja zamówienia) rozważyć implementację sagi z kompensacjami
2. **Idempotency** - każdy command powinien być idempotentny (retry-safe)
3. **Event Sourcing** - w przyszłości Cart Service może używać event sourcing zamiast state-based
4. **Distributed Tracing** - dodać correlation_id do wszystkich requestów i eventów
5. **Health Checks** - `/health` endpoint w każdym serwisie dla Docker healthcheck

