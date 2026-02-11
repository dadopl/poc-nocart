# E2E Tests - Dokumentacja

## Przegląd

Testy E2E (End-to-End) weryfikują poprawność działania całego systemu mikroserwisów poprzez symulację rzeczywistego flow zakupowego użytkownika.

## Struktura

```
tests/
├── composer.json
├── phpunit.xml
├── run-e2e.sh              # Skrypt uruchamiający (Linux/Mac)
├── run-e2e.bat             # Skrypt uruchamiający (Windows)
├── src/
│   └── Client/
│       ├── BaseServiceClient.php
│       ├── CartClient.php
│       ├── ShippingClient.php
│       ├── PaymentClient.php
│       ├── PromotionClient.php
│       ├── ServicesClient.php
│       └── CheckoutClient.php
└── tests/
    └── Functional/
        ├── E2ETestCase.php
        ├── HealthCheckTest.php
        └── CheckoutFlowTest.php
```

## Testy

### HealthCheckTest

Weryfikuje czy wszystkie serwisy są uruchomione i odpowiadają:

- `testCartServiceIsHealthy`
- `testShippingServiceIsHealthy`
- `testPaymentServiceIsHealthy`
- `testCheckoutServiceIsHealthy`
- `testPromotionServiceIsHealthy`
- `testServicesServiceIsHealthy`
- `testAllServicesAreHealthy`

### CheckoutFlowTest

Główny test funkcjonalny pokrywający cały flow z `flow.md`:

#### `testCompleteCheckoutFlow`

Kompletny scenariusz zakupowy:

1. **Dodanie laptopa** (5999 PLN) - weryfikacja koszyka
2. **Dodanie gwarancji** (299 PLN) - przypisana do laptopa
3. **Dodanie torby** (149 PLN) - akcesorium do laptopa
4. **Sprawdzenie promocji** - dostępna "2 w cenie 1.5"
5. **Aplikacja promocji** - zwiększenie ilości do 2
6. **Usługa SMS** (2 PLN) - standalone service
7. **Wybór dostawy** - kurier DPD
8. **Adres dostawy** - Warszawa
9. **Express delivery** - +29.99 PLN
10. **Dodanie lodówki** (2999 PLN)
11. **Usługa wniesienia** (99 PLN) - dla lodówki
12. **Wybór płatności** - BLIK
13. **Dane klienta** - email, imię, nazwisko, telefon
14. **Zgody** - terms, privacy
15. **Finalizacja** - utworzenie zamówienia
16. **Płatność BLIK** - symulacja kodu 123456
17. **Weryfikacja** - status completed, koszyk wyczyszczony

#### `testPartialCheckoutFlowWithPromoCode`

Test z kodem rabatowym SAVE10:
- Dodanie produktu
- Aplikacja kodu "SAVE10" (10% zniżki)
- Wybór dostawy InPost
- Weryfikacja zniżki

#### `testCheckoutFlowWithMultipleServices`

Test usług dodatkowych:
- Dodanie produktu AGD
- Pobranie dostępnych usług dla kategorii
- Usługa wniesienia (service_item)
- Usługa SMS (service_standalone)

#### `testCheckoutValidationErrors`

Test walidacji:
- Próba finalizacji bez kompletnych danych
- Weryfikacja brakujących wymagań

#### `testShippingMethodSelection`

Test wyboru metod dostawy:
- Pobranie dostępnych metod
- Wybór każdej metody kolejno
- Weryfikacja zapisania wyboru

#### `testPaymentMethodSelection`

Test wyboru metod płatności:
- Pobranie dostępnych metod
- Wybór BLIK
- Weryfikacja statusu

#### `testCartOperations`

Test operacji na koszyku:
- Dodanie produktu
- Zmiana ilości
- Usunięcie produktu
- Czyszczenie koszyka

## Uruchamianie

### Wymagania

- PHP 8.4+
- Composer
- Uruchomione wszystkie mikroserwisy Docker

### Instalacja

```bash
cd tests/
composer install
```

### Uruchamianie testów

```bash
# Wszystkie testy E2E
./run-e2e.sh

# Tylko health check
./run-e2e.sh --health

# Tylko główny flow
./run-e2e.sh --flow

# Konkretny test
./run-e2e.sh --filter testCompleteCheckoutFlow

# Z debug output
E2E_DEBUG=true ./run-e2e.sh
```

### Windows

```batch
run-e2e.bat
run-e2e.bat --health
run-e2e.bat --flow
```

### Docker

```bash
# Uruchom stack
docker-compose up -d

# Poczekaj na health checks
sleep 30

# Uruchom testy
cd tests/
./run-e2e.sh
```

## Konfiguracja

### Zmienne środowiskowe

| Zmienna | Domyślna wartość | Opis |
|---------|------------------|------|
| `CART_SERVICE_URL` | `http://localhost:8001` | URL Cart Service |
| `SHIPPING_SERVICE_URL` | `http://localhost:8002` | URL Shipping Service |
| `PAYMENT_SERVICE_URL` | `http://localhost:8003` | URL Payment Service |
| `CHECKOUT_SERVICE_URL` | `http://localhost:8004` | URL Checkout Service |
| `PROMOTION_SERVICE_URL` | `http://localhost:8005` | URL Promotion Service |
| `SERVICES_SERVICE_URL` | `http://localhost:8006` | URL Services Service |
| `KAFKA_SYNC_WAIT_MS` | `500` | Czas oczekiwania na sync Kafka (ms) |
| `E2E_DEBUG` | `false` | Włącz debug output |

### phpunit.xml

Konfiguracja znajduje się w `phpunit.xml`. Zmienne środowiskowe można też ustawić tam.

## Troubleshooting

### Testy failują z timeout

Zwiększ `KAFKA_SYNC_WAIT_MS`:

```bash
KAFKA_SYNC_WAIT_MS=2000 ./run-e2e.sh
```

### Serwis nie odpowiada

1. Sprawdź czy Docker działa: `docker ps`
2. Sprawdź logi: `docker-compose logs <service-name>`
3. Uruchom health check: `./run-e2e.sh --health`

### Cart nie jest czyszczony

To normalne zachowanie w POC - koszyk może nie być automatycznie czyszczony po płatności. Test obsługuje ten przypadek.

## Rozszerzanie

### Dodanie nowego testu

```php
#[Test]
public function testMyNewScenario(): void
{
    // Arrange
    $this->cartClient->addItem(...);
    
    // Act
    $result = $this->checkoutClient->finalize();
    
    // Assert
    $this->assertArrayHasKey('order_id', $result);
}
```

### Dodanie nowego klienta

1. Utwórz klasę w `src/Client/`
2. Rozszerz `BaseServiceClient`
3. Dodaj do `E2ETestCase::setUp()`

