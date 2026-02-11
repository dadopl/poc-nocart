Mikroserwisy = Bounded Contexts
Lista mikroserwisów:

Cart Service (Cart Context)
Shipping Service (Shipping Context)
Payment Service (Payment Context)
Checkout Service (Checkout Session Orchestrator)

Komunikacja między mikroserwisami
TAK - przez Kafkę (event-driven)
Event flow:
Cart Service
→ Kafka topic: cart-events
→ Event: CartItemAdded
Konsumenci:

Shipping Service (invalidate cache)
Payment Service (invalidate cache)
Checkout Service (update totals)

Shipping Service
→ Kafka topic: shipping-events
→ Event: ShippingMethodSelected
Konsumenci:

Payment Service (invalidate cache - pobranie zależy od transportu)
Checkout Service (update totals)


Payment Service
→ Kafka topic: payment-events
→ Event: PaymentMethodSelected
Konsumenci:

Checkout Service (update totals)


Warranty Service
→ Kafka topic: warranty-events
→ Event: WarrantySelected
Konsumenci:

Checkout Service (update totals)


Każdy mikroserwis:

Publishuje eventy do swojego topic
Konsumuje eventy z innych topics (consumer group)
Async communication (eventual consistency)
Zero bezpośrednich wywołań HTTP między serwisami


# Funkcjonalności Mikroserwisów (bez Warranty Service)

## 1. CART SERVICE

**Odpowiedzialność:**
- Dodawanie pozycji do koszyka (oferty, gwarancje, akcesoria)
- Usuwanie pozycji
- Zmiana ilości
- Pobieranie koszyka (materialized view)
- Async enrichment pozycji (stany, promocje, ceny)

**API endpoints:**
- POST /cart/items - dodaj pozycję
- DELETE /cart/items/{id} - usuń pozycję
- PATCH /cart/items/{id} - zmień ilość
- GET /cart - pobierz koszyk

**Publishowane eventy:**
- CartItemAdded
- CartItemRemoved
- CartItemQuantityChanged

**Redis keys:**
- cart:events:{user_id} (stream)
- cart:view:{user_id} (materialized view)
- cart:counter:{user_id} (licznik pozycji)

---

## 2. SHIPPING SERVICE

**Odpowiedzialność:**
- Obliczanie dostępnych metod transportu
- Wybór metody transportu
- Zarządzanie adresem dostawy
- Kalendarz dostaw (integracja z external API)
- Wybór dnia dostawy
- Shipping addons (np. dostawa w niedzielę)

**API endpoints:**
- GET /shipping/available - dostępne metody transportu
- POST /shipping/select - wybierz metodę
- POST /shipping/address - ustaw adres dostawy
- GET /shipping/calendar - dostępne dni dostawy
- POST /shipping/set-date - wybierz dzień

**Publishowane eventy:**
- ShippingMethodSelected
- ShippingAddressProvided
- DeliveryDateSelected

**Redis keys:**
- shipping:methods:{cart_hash} (cache metod transportu)
- shipping:session:{session_id} (wybrana metoda)
- shipping:address:{session_id} (adres dostawy)
- shipping:addons:{session_id} (dodatki jak niedziela)
- calendar:{method}:{postal_code} (cache kalendarza)

**External dependencies:**
- Calendar API (circuit breaker)

---

## 3. PAYMENT SERVICE
W sekcji "PAYMENT SERVICE" napisałem tylko:
- Obliczanie dostępnych metod płatności
- Wybór metody płatności
- Walidacja metody względem reguł biznesowych
---

## Poprawiona funkcjonalność PAYMENT SERVICE:

**Odpowiedzialność:**
- Obliczanie dostępnych metod płatności
- Wybór metody płatności
- Walidacja metody względem reguł biznesowych
- **Inicjalizacja płatności online**
- **Integracja z payment gateway (Przelewy24, PayU, Stripe)**
- **Obsługa webhooków z gateway**
- **Weryfikacja statusu płatności**
- **Handling stanów: success, failed, pending, refund**

**API endpoints (rozszerzone):**
- GET /payment/available
- POST /payment/select
- **POST /payment/initialize**
- **POST /payment/webhook/{gateway}**
- **GET /payment/status/{transaction_id}**

**Publishowane eventy (rozszerzone):**
- PaymentMethodSelected
- **PaymentInitialized**
- **PaymentSucceeded**
- **PaymentFailed**
- **PaymentPending**

**External dependencies:**
- Payment gateway APIs (circuit breaker)


---

## 4. CHECKOUT SERVICE (Orchestrator)

**Odpowiedzialność:**
- Agregacja danych z wszystkich serwisów
- Zarządzanie sesją checkout
- Materialized totals view
- Dane klienta (email, faktura)
- Zgody formalne
- Finalizacja zamówienia (real-time validation)

**API endpoints:**
- GET /checkout/summary - pełne podsumowanie checkout
- POST /checkout/customer-data - dane klienta
- POST /checkout/consents - zgody
- POST /checkout/finalize - finalizacja zamówienia

**Publishowane eventy:**
- OrderCreated
- CheckoutCompleted

**Konsumowane eventy:**
- CartItemAdded/Removed (update totals)
- ShippingMethodSelected (update totals)
- DeliveryDateSelected (update totals)
- PaymentMethodSelected (update totals)

**Redis keys:**
- checkout:totals:{session_id} (materialized totals view)
- checkout:customer:{session_id} (dane klienta)
- checkout:consents:{session_id} (zgody)
- checkout:session:{session_id} (meta-dane sesji)

**Logika finalizacji:**
- Real-time validation (stany, ceny, dostępność)
- Atomowa rezerwacja stanów
- Utworzenie zamówienia w DB
- Trigger payment gateway

---

## PROMOTION SERVICE
   Odpowiedzialność:

Obliczanie dostępnych promocji koszyka
Aplikowanie promocji koszyka (2 za 50%, 3+1 gratis, kody rabatowe)
Walidacja kodów promocyjnych
Przeliczanie rabatów
Zapisywanie użytych promocji

API endpoints:

GET /promotions/available?cart_id=xxx - dostępne promocje dla koszyka
POST /promotions/apply - zastosuj promocję
POST /promotions/apply-code - zastosuj kod rabatowy
DELETE /promotions/{id} - usuń promocję

Publishowane eventy:

PromotionApplied
PromotionRemoved
PromoCodeApplied
PromoCodeInvalid

Konsumowane eventy:

CartItemAdded (przelicz dostępne promocje)
CartItemRemoved (przelicz dostępne promocje)
CartItemQuantityChanged (przelicz dostępne promocje)

Redis keys:

promotions:available:{cart_hash} (cache dostępnych promocji)
promotions:applied:{session_id} (zastosowane promocje)
promo_codes:usage:{code} (ile razy użyty kod)

Logika:

Pobiera cart snapshot z Cart Service
Analizuje pozycje pod kątem reguł promocyjnych
Oblicza rabaty
Nie modyfikuje koszyka bezpośrednio
Checkout Service agreguje rabaty do totals


## SERVICES SERVICE
   Odpowiedzialność:
Wyświetlanie dostępnych usług dla koszyka
Usługi związane z pozycjami (wniesienie, montaż, instalacja)
Usługi standalone (powiadomienie SMS, opakowanie prezentowe)
Walidacja dostępności usług
Zarządzanie cenami usług

API endpoints:
GET /services/available?cart_id=xxx - dostępne usługi dla koszyka
GET /services/for-item/{item_id} - usługi dla konkretnej pozycji
GET /services/standalone - usługi niezależne

Typy usług:
service_item - związana z pozycją (parent_item_id)

wniesienie (dla AGD/RTV)
montaż (dla mebli)
instalacja (dla sprzętu)


service_standalone - niezależna (brak parent)
powiadomienie SMS
opakowanie prezentowe
przedłużona obsługa



Publishowane eventy:
ServiceAvailabilityCalculated

Konsumowane eventy:
CartItemAdded (sprawdź dostępne usługi dla nowej pozycji)
CartItemRemoved (usuń powiązane usługi)

Redis keys:
services:available:{cart_hash} (cache dostępnych usług)
services:rules (statyczne reguły dostępności)

Logika:
Pobiera cart snapshot z Cart Service
Analizuje pozycje (kategoria, waga, wymiary)
Zwraca dostępne usługi z cenami
User dodaje usługę → Cart Service (POST /cart/items type=service)

Configuration:
    services_rules.yaml (jakie usługi dla jakich kategorii)

## Komunikacja Kafka

**Topics:**
- cart-events
- shipping-events
- payment-events
- checkout-events

**RAZEM: 4 mikroserwisy**

Każdy serwis:
- Publishuje do swojego topic
- Konsumuje z innych topics (consumer groups)
- Async, eventual consistency
