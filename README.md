# Nocart - Microservices E-commerce Platform

## Architektura

System składa się z 6 mikroserwisów komunikujących się przez Kafka:

| Serwis | Port | Opis |
|--------|------|------|
| Cart | 38001 | Zarządzanie koszykiem |
| Shipping | 38002 | Metody dostawy |
| Payment | 38003 | Płatności |
| Checkout | 38004 | Orkiestrator, totals |
| Promotion | 38005 | Promocje i kody rabatowe |
| Services | 38006 | Usługi dodatkowe |

## Wymagania

- Docker & Docker Compose
- Make (opcjonalnie)

**Uwaga:** Nie potrzebujesz lokalnego PHP ani Composera! Wszystko działa w kontenerach Docker.

## Instalacja

### Opcja 1: Automatyczna instalacja (Rekomendowana)

```bash
# Uruchom skrypt instalacyjny
./install.sh
```

Skrypt automatycznie:
1. ✅ Zbuduje obrazy Docker
2. ✅ Zainstaluje zależności Composer w każdym mikroserwisie (przez Docker)
3. ✅ Zainstaluje zależności w SharedKernel
4. ✅ Zainstaluje zależności w E2E testach

Po instalacji wystarczy:
```bash
# Uruchom stack
docker-compose up -d

# Poczekaj na health checks
sleep 30

# Utwórz topiki Kafka
docker-compose exec redpanda rpk topic create cart-events shipping-events payment-events promotion-events services-events checkout-events

# Uruchom E2E testy
cd tests && ./run-e2e.sh
```

### Opcja 2: Makefile

```bash
# Zbuduj obrazy i zainstaluj dependencies
make build
make composer-install-all

# Uruchom stack
make up

# Utwórz topiki Kafka
make kafka-topics

# Sprawdź health
make health-check
```

## Zarządzanie zależnościami Composer

### Dodawanie/usuwanie paczek

Użyj pomocniczego skryptu `composer-helper.sh`:

```bash
# Dodaj nową paczkę do Cart Service
./composer-helper.sh cart require symfony/cache

# Zaktualizuj zależności w Checkout Service
./composer-helper.sh checkout update

# Usuń paczkę z Promotion Service
./composer-helper.sh promotion remove package-name

# Regeneruj autoload w SharedKernel
./composer-helper.sh shared dump-autoload
```

Dostępne serwisy: `shared`, `cart`, `shipping`, `payment`, `promotion`, `services`, `checkout`, `tests`

### Ręczne wywołania composer przez Docker

```bash
# Cart Service
docker-compose run --rm --no-deps cart-php composer install

# SharedKernel
docker run --rm -v "$(pwd)/SharedKernel:/app" -w /app composer:2 install

# E2E Tests
docker run --rm -v "$(pwd)/tests:/app" -w /app composer:2 install
```

## Dostępne usługi

- **Redpanda Console**: http://localhost:38080
- **Cart API**: http://localhost:38001
- **Shipping API**: http://localhost:38002
- **Payment API**: http://localhost:38003
- **Checkout API**: http://localhost:38004
- **Promotion API**: http://localhost:38005
- **Services API**: http://localhost:38006

## Komendy Docker

```bash
# Logi wszystkich serwisów
make logs

# Logi konkretnego serwisu
make logs-cart

# Shell w kontenerze
make shell-cart

# Redis CLI
make redis-cli
```

## Testy

```bash
# Wszystkie testy
make test-all

# Testy konkretnego serwisu
make test-cart
```

## Struktura projektu

```
nocart/
├── docker-compose.yml
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   └── conf.d/
│   └── nginx/
│       ├── cart.conf
│       ├── shipping.conf
│       └── ...
├── SharedKernel/          # Wspólne kontrakty
├── Cart/                  # Cart Service
├── Shipping/              # Shipping Service
├── Payment/               # Payment Service
├── Checkout/              # Checkout Service
├── Promotion/             # Promotion Service
└── Services/              # Services Service
```

# poc-nocart
