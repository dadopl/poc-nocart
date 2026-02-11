# Nocart - Microservices E-commerce Platform

## Architektura

System składa się z 6 mikroserwisów komunikujących się asynchronicznie przez Kafka (Redpanda):

| Serwis | Port | Opis |
|--------|------|------|
| Cart | 38001 | Zarządzanie koszykiem |
| Shipping | 38002 | Metody dostawy |
| Payment | 38003 | Płatności |
| Checkout | 38004 | Orkiestrator, totals |
| Promotion | 38005 | Promocje i kody rabatowe |
| Services | 38006 | Usługi dodatkowe |

### Zasady komunikacji

- **Każdy mikroserwis ma własny Redis** - nie sięga do danych innych serwisów
- **Komunikacja przez eventy Kafka** - mikroserwisy budują lokalne projekcje na podstawie eventów
- **Checkout Service** - agreguje dane z wszystkich serwisów przez nasłuchiwanie eventów

---

## Flow procesu zakupowego

### 1. Dodanie produktu do koszyka

**Akcja użytkownika:** Klika "Dodaj do koszyka" (np. Laptop 5999 PLN)

```
┌──────────────┐     POST /cart/items      ┌──────────────┐
│   Frontend   │ ───────────────────────► │ Cart Service │
└──────────────┘                           └──────┬───────┘
                                                  │
                                                  │ Publish: CartItemAdded
                                                  ▼
                                           ┌──────────────┐
                                           │    Kafka     │
                                           │ cart-events  │
                                           └──────┬───────┘
                    ┌─────────────────────────────┼─────────────────────────────┐
                    │                             │                             │
                    ▼                             ▼                             ▼
            ┌──────────────┐              ┌──────────────┐              ┌──────────────┐
            │   Shipping   │              │  Promotion   │              │   Checkout   │
            │   Service    │              │   Service    │              │   Service    │
            └──────────────┘              └──────────────┘              └──────────────┘
            invalidate cache              przelicz promocje            update totals
```

**Cart Service:**
- Zapisuje item do `cart:{user_id}` w Redis
- Publikuje event `CartItemAdded` do Kafka

**Konsumenci (nasłuchują na `cart-events`):**
| Serwis | Akcja |
|--------|-------|
| Shipping | Invaliduje cache metod dostawy (waga się zmieniła) |
| Promotion | Przelicza dostępne promocje |
| Services | Sprawdza dostępne usługi dla produktu |
| **Checkout** | **Aktualizuje lokalną projekcję `CheckoutOrder`** |

---

### 2. Dodanie gwarancji/akcesorium

**Akcja użytkownika:** Dodaje gwarancję lub akcesorium do produktu

```
Cart Service                    Kafka                      Checkout Service
     │                            │                              │
     │  CartItemAdded             │                              │
     │  (type: "warranty")        │                              │
     │ ────────────────────────►  │                              │
     │                            │  ────────────────────────►   │
     │                            │                              │
     │                            │          applyCartItemAdded()│
     │                            │          recalculateTotal()  │
     │                            │          save to Redis       │
```

**Payload eventu:**
```json
{
  "cart_id": "019c4ddb-e057-739b-96d6-25acbc7a1117",
  "item_id": "019c4ddc-41ae-7992-be95-afade7e86ad8",
  "item_type": "warranty",
  "offer_id": 456,
  "quantity": 1,
  "price_amount": 29900,
  "price_currency": "PLN",
  "parent_item_id": "019c4ddb-e057-7407-96d6-25acbd53e6d9"
}
```

**Checkout Service:**
- Odbiera event przez `ExternalEventHandler`
- Wywołuje `CheckoutOrder::applyCartItemAdded()`
- Przelicza `subtotal` sumując wszystkie pozycje
- Zapisuje do `checkout:order:{session_id}` w Redis

---

### 3. Wybór metody dostawy

**Akcja użytkownika:** Wybiera metodę dostawy (np. Kurier DPD)

```
┌──────────────┐  POST /shipping/select   ┌──────────────┐
│   Frontend   │ ─────────────────────►   │   Shipping   │
└──────────────┘                          │   Service    │
                                          └──────┬───────┘
                                                 │
                                                 │ Publish: ShippingMethodSelected
                                                 ▼
                                          ┌──────────────┐
                                          │    Kafka     │
                                          │shipping-event│
                                          └──────┬───────┘
                                                 │
                                                 ▼
                                          ┌──────────────┐
                                          │   Checkout   │
                                          │   Service    │
                                          └──────────────┘
                                          applyShippingMethodSelected()
                                          update shipping_cost
```

**Shipping Service:**
- Zapisuje metodę do `shipping:session:{session_id}`
- Publikuje event `ShippingMethodSelected`

**Checkout Service:**
- Aktualizuje `shippingSnapshot` w `CheckoutOrder`
- Przelicza `grand_total`

---

### 4. Zastosowanie promocji

**Akcja użytkownika:** Aplikuje kod promocyjny

```
┌──────────────┐  POST /promotions/apply  ┌──────────────┐
│   Frontend   │ ─────────────────────►   │  Promotion   │
└──────────────┘                          │   Service    │
                                          └──────┬───────┘
                                                 │
                                                 │ Publish: PromotionApplied
                                                 ▼
                                          ┌──────────────┐
                                          │    Kafka     │
                                          │promotion-evt │
                                          └──────┬───────┘
                                                 │
                                                 ▼
                                          ┌──────────────┐
                                          │   Checkout   │
                                          │   Service    │
                                          └──────────────┘
                                          applyPromotionApplied()
                                          update promotion_discount
```

**Promotion Service:**
- Waliduje i oblicza rabat
- Zapisuje do `promotion:session:{session_id}`
- Publikuje event `PromotionApplied`

**Checkout Service:**
- Aktualizuje `promotionSnapshot`
- Przelicza `grand_total` z uwzględnieniem rabatu

---

### 5. Wybór płatności

**Akcja użytkownika:** Wybiera metodę płatności (np. BLIK)

```
┌──────────────┐  POST /payment/select    ┌──────────────┐
│   Frontend   │ ─────────────────────►   │   Payment    │
└──────────────┘                          │   Service    │
                                          └──────┬───────┘
                                                 │
                                                 │ Publish: PaymentMethodSelected
                                                 ▼
                                          ┌──────────────┐
                                          │    Kafka     │
                                          │payment-events│
                                          └──────┬───────┘
                                                 │
                                                 ▼
                                          ┌──────────────┐
                                          │   Checkout   │
                                          │   Service    │
                                          └──────────────┘
                                          applyPaymentMethodSelected()
```

---

### 6. Finalizacja zamówienia

**Akcja użytkownika:** Klika "Zamów i zapłać"

```
┌──────────────┐  POST /checkout/finalize ┌──────────────┐
│   Frontend   │ ─────────────────────►   │   Checkout   │
└──────────────┘                          │   Service    │
                                          └──────┬───────┘
                                                 │
                                                 │ Publish: OrderCreated
                                                 ▼
                                          ┌──────────────┐
                                          │    Kafka     │
                                          │checkout-evts │
                                          └──────┬───────┘
                    ┌─────────────────────────────┼─────────────────────────────┐
                    │                             │                             │
                    ▼                             ▼                             ▼
            ┌──────────────┐              ┌──────────────┐              ┌──────────────┐
            │   Payment    │              │   Shipping   │              │     Cart     │
            │   Service    │              │   Service    │              │   Service    │
            └──────────────┘              └──────────────┘              └──────────────┘
            initialize payment            prepare shipment             clear cart
```

**Checkout Service:**
- Waliduje kompletność danych (koszyk, dostawa, płatność, zgody)
- Tworzy zamówienie
- Publikuje event `OrderCreated`

**Payment Service (konsumuje `OrderCreated`):**
- Inicjuje płatność w bramce (np. Przelewy24)
- Publikuje `PaymentInitialized`

---

### 7. Potwierdzenie płatności

```
     Payment Gateway              Payment Service              Kafka
           │                            │                        │
           │  Webhook: success          │                        │
           │ ────────────────────────►  │                        │
           │                            │   PaymentSucceeded     │
           │                            │ ──────────────────────►│
           │                            │                        │
                                                                 │
                    ┌────────────────────────────────────────────┘
                    │
                    ▼
            ┌──────────────┐
            │   Checkout   │
            │   Service    │
            └──────────────┘
            update status = PAID
            trigger email
            
            ┌──────────────┐
            │     Cart     │
            │   Service    │
            └──────────────┘
            clear cart
```

---

## Struktura danych Checkout

Checkout Service buduje lokalną projekcję `CheckoutOrder` na podstawie eventów:

```php
CheckoutOrder {
    sessionId: string
    
    cartSnapshot: {
        items: [
            {item_id, item_type, offer_id, quantity, price_amount, parent_item_id}
        ],
        total_cents: int
    }
    
    shippingSnapshot: {
        method_id, method_name, cost, address, delivery_date
    }
    
    promotionSnapshot: {
        applied: [...],
        codes: [...],
        total_discount: int
    }
    
    servicesSnapshot: {
        selected: [...],
        total_cost: int
    }
    
    paymentSnapshot: {
        method_id, method_name, status, transaction_id
    }
}
```

**Endpoint `/checkout/totals` zwraca:**
```json
{
  "totals": {
    "subtotal": {"amount": 644700, "currency": "PLN"},
    "shipping_cost": {"amount": 1599, "currency": "PLN"},
    "promotion_discount": {"amount": 0, "currency": "PLN"},
    "services_cost": {"amount": 0, "currency": "PLN"},
    "grand_total": {"amount": 646299, "currency": "PLN"}
  }
}
```

---

## Obsługiwane typy eventów

| Event | Źródło | Konsumenci |
|-------|--------|------------|
| `CartItemAdded` | Cart | Checkout, Shipping, Promotion, Services |
| `CartItemRemoved` | Cart | Checkout, Shipping, Promotion |
| `CartItemQuantityChanged` | Cart | Checkout, Promotion |
| `CartCleared` | Cart | Checkout |
| `ShippingMethodSelected` | Shipping | Checkout |
| `ShippingAddressProvided` | Shipping | Checkout |
| `PromotionApplied` | Promotion | Checkout |
| `PromotionRemoved` | Promotion | Checkout |
| `PaymentMethodSelected` | Payment | Checkout |
| `PaymentSucceeded` | Payment | Checkout, Cart |
| `PaymentFailed` | Payment | Checkout |
| `OrderCreated` | Checkout | Payment, Shipping |

---

## Wymagania
- Docker & Docker Compose
- Make (opcjonalnie)


## Dostępne usługi
- **Redpanda Console**: http://localhost:38080
- **Cart API**: http://localhost:38001
- **Shipping API**: http://localhost:38002
- **Payment API**: http://localhost:38003
- **Checkout API**: http://localhost:38004
- **Promotion API**: http://localhost:38005
- **Services API**: http://localhost:38006

