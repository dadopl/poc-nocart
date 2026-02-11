# NoCart Postman Collection

## Opis

Kolekcja Postman do testowania kompletnego flow zakupowego w architekturze mikroserwisowej NoCart.

## Import

1. Otwórz Postman
2. Kliknij **Import** → **Upload Files**
3. Wybierz plik `NoCart-Checkout-Flow.postman_collection.json`

## Konfiguracja

Kolekcja używa zmiennych środowiskowych. Domyślne wartości:

| Zmienna | Wartość domyślna | Opis |
|---------|-----------------|------|
| `cart_url` | `http://localhost:38001` | URL Cart Service |
| `shipping_url` | `http://localhost:38002` | URL Shipping Service |
| `payment_url` | `http://localhost:38003` | URL Payment Service |
| `checkout_url` | `http://localhost:38004` | URL Checkout Service |
| `promotion_url` | `http://localhost:38005` | URL Promotion Service |
| `services_url` | `http://localhost:38006` | URL Services Service |

## Struktura kolekcji

```
0. Setup
   └── Generate Session & User IDs

1. Health Checks
   ├── Cart Service Health
   ├── Shipping Service Health
   ├── Payment Service Health
   ├── Checkout Service Health
   ├── Promotion Service Health
   └── Services Service Health

2. Cart Operations
   ├── Add Laptop to Cart (5999 PLN)
   ├── Add Warranty to Laptop (299 PLN)
   ├── Add Bag as Accessory (149 PLN)
   ├── Get Cart
   └── Change Laptop Quantity to 2

3. Shipping
   ├── Get Available Shipping Methods
   ├── Select DPD Courier
   ├── Set Shipping Address
   ├── Set Express Delivery Date
   └── Get Shipping Session

4. Promotions
   ├── Get Available Promotions
   ├── Apply Promotion 2x50
   ├── Apply Promo Code SAVE10
   └── Get Promotion Session

5. Services
   ├── Get Available Services for AGD
   └── Get Standalone Services

6. Payment
   ├── Get Available Payment Methods
   ├── Select BLIK Payment
   └── Get Payment Status

7. Checkout
   ├── Get Checkout Totals
   ├── Set Customer Data
   ├── Set Consents
   ├── Get Checkout Totals (Before Finalize)
   ├── Finalize Checkout
   └── Get Checkout Summary

8. Payment Confirmation
   ├── Initialize Payment
   ├── Confirm BLIK Payment
   └── Get Final Payment Status

9. Verification
   ├── Verify Checkout Completed
   └── Verify Cart is Empty

10. Cleanup
    └── Clear Cart
```

## Uruchomienie

### Krok po kroku

1. Uruchom **0. Setup → Generate Session & User IDs** - generuje unikalne ID sesji i użytkownika
2. Uruchom foldery 1-10 w kolejności

### Automatyczne (Collection Runner)

1. Kliknij prawym na kolekcję → **Run collection**
2. Kliknij **Run NoCart - Checkout Flow E2E**
3. Obserwuj wyniki testów

## Testy

Każdy request zawiera testy automatyczne sprawdzające:
- Status odpowiedzi HTTP
- Strukturę odpowiedzi JSON
- Wartości pól

## Zmienne dynamiczne

Kolekcja automatycznie zapisuje:
- `session_id` - ID sesji (generowane w Setup)
- `user_id` - ID użytkownika (generowane w Setup)
- `laptop_item_id` - ID laptopa w koszyku
- `order_id` - ID zamówienia (po finalizacji)
- `delivery_date` - Data dostawy (jutro)

## Wymagania

- Docker compose uruchomiony z wszystkimi serwisami
- Porty 38001-38006 dostępne

```bash
# Uruchom serwisy
docker-compose up -d

# Sprawdź czy działają
curl http://localhost:38001/health
curl http://localhost:38002/health
curl http://localhost:38003/health
curl http://localhost:38004/health
curl http://localhost:38005/health
curl http://localhost:38006/health
```
