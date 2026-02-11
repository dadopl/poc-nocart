# E2E Tests - Quick Start

## Uruchomienie testów

### Z głównego folderu projektu (ZALECANE):
```bash
make test
```
Testy uruchamiane w kontenerze Docker, łączą się z serwisami przez Docker network.

### Ręcznie z hosta (wymaga ustawienia URL):
```bash
cd tests
CART_SERVICE_URL=http://localhost:38001 \
SHIPPING_SERVICE_URL=http://localhost:38002 \
PAYMENT_SERVICE_URL=http://localhost:38003 \
CHECKOUT_SERVICE_URL=http://localhost:38004 \
PROMOTION_SERVICE_URL=http://localhost:38005 \
SERVICES_SERVICE_URL=http://localhost:38006 \
./run-e2e.sh
```

## Wymagania

Serwisy muszą być uruchomione:
```bash
make up
make kafka-topics
```

Porty serwisów:
- Cart: http://localhost:38001
- Shipping: http://localhost:38002
- Payment: http://localhost:38003
- Checkout: http://localhost:38004
- Promotion: http://localhost:38005
- Services: http://localhost:38006

## Sprawdzenie statusu

```bash
make health-check
```

## Troubleshooting

### Testy failują
```bash
# Sprawdź logi
make logs

# Sprawdź czy consumers działają
docker compose logs checkout-php | grep consumer
```

### Zwiększ timeout dla Kafka
```bash
KAFKA_SYNC_WAIT_MS=1000 make test-e2e
```
