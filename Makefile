.PHONY: build up down restart logs shell-cart shell-shipping shell-payment shell-checkout shell-promotion shell-services composer-install kafka-topics setup help

# Default target - show help
help:
	@echo "Nocart Makefile Commands:"
	@echo ""
	@echo "  make setup              - Run installation script (build + install dependencies)"
	@echo "  make build              - Build Docker images"
	@echo "  make up                 - Start all services"
	@echo "  make down               - Stop all services"
	@echo "  make restart            - Restart all services"
	@echo ""
	@echo "  make test               - Run E2E tests"
	@echo "  make test-e2e           - Run E2E tests"
	@echo "  make test-e2e-debug     - Run E2E tests with debug output"
	@echo "  make test-all           - Run all tests (unit + E2E)"
	@echo ""
	@echo "  make composer-install-all   - Install composer dependencies in all services"
	@echo "  make composer-cart          - Install composer dependencies in Cart service"
	@echo "  make composer-shared        - Install composer dependencies in SharedKernel"
	@echo ""
	@echo "  make kafka-topics       - Create Kafka topics"
	@echo "  make kafka-topics-list  - List Kafka topics"
	@echo ""
	@echo "  make logs               - Show logs from all services"
	@echo "  make logs-cart          - Show logs from Cart service"
	@echo "  make shell-cart         - Open shell in Cart service container"
	@echo ""
	@echo "  make health-check       - Check health of all services"
	@echo ""

# Quick install - builds and installs all dependencies
setup:
	@echo "Running installation script..."
	./install.sh

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down && docker compose up -d

logs:
	docker compose logs -f

logs-cart:
	docker compose logs -f cart-php cart-nginx

logs-shipping:
	docker compose logs -f shipping-php shipping-nginx

logs-payment:
	docker compose logs -f payment-php payment-nginx

logs-checkout:
	docker compose logs -f checkout-php checkout-nginx

logs-promotion:
	docker compose logs -f promotion-php promotion-nginx

logs-services:
	docker compose logs -f services-php services-nginx

shell-cart:
	docker compose exec cart-php sh

shell-shipping:
	docker compose exec shipping-php sh

shell-payment:
	docker compose exec payment-php sh

shell-checkout:
	docker compose exec checkout-php sh

shell-promotion:
	docker compose exec promotion-php sh

shell-services:
	docker compose exec services-php sh

# Composer install commands
composer-shared:
	docker compose run --rm --entrypoint "" -w /var/www/shared cart-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-cart:
	docker compose run --rm cart-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-shipping:
	docker compose run --rm shipping-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-payment:
	docker compose run --rm payment-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-checkout:
	docker compose run --rm checkout-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-promotion:
	docker compose run --rm promotion-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-services:
	docker compose run --rm services-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-tests:
	docker compose run --rm --entrypoint "" -v "$$(pwd)/tests:/var/www/html" -w /var/www/html cart-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

composer-install-all: composer-shared composer-cart composer-shipping composer-payment composer-checkout composer-promotion composer-services composer-tests

kafka-topics:
	docker compose exec redpanda rpk topic create cart-events shipping-events payment-events checkout-events promotion-events services-events --partitions 3

kafka-topics-list:
	docker compose exec redpanda rpk topic list

redis-cli:
	docker compose exec redis redis-cli

test-cart:
	docker compose exec cart-php ./vendor/bin/phpunit

test-shipping:
	docker compose exec shipping-php ./vendor/bin/phpunit

test-payment:
	docker compose exec payment-php ./vendor/bin/phpunit

test-checkout:
	docker compose exec checkout-php ./vendor/bin/phpunit

test-promotion:
	docker compose exec promotion-php ./vendor/bin/phpunit

test-services:
	docker compose exec services-php ./vendor/bin/phpunit

test-e2e:
	@echo "Running E2E tests..."
	docker compose run --rm --entrypoint "" -v "$$(pwd)/tests:/var/www/html" -w /var/www/html cart-php sh ./run-e2e.sh

test-e2e-debug:
	@echo "Running E2E tests with debug output..."
	docker compose run --rm --entrypoint "" -v "$$(pwd)/tests:/var/www/html" -w /var/www/html -e E2E_DEBUG=true cart-php sh ./run-e2e.sh

test: test-e2e

test-all: test-cart test-shipping test-payment test-checkout test-promotion test-services test-e2e

health-check:
	@echo "Cart Service:" && curl -s http://localhost:38001/health || echo "DOWN"
	@echo "Shipping Service:" && curl -s http://localhost:38002/health || echo "DOWN"
	@echo "Payment Service:" && curl -s http://localhost:38003/health || echo "DOWN"
	@echo "Checkout Service:" && curl -s http://localhost:38004/health || echo "DOWN"
	@echo "Promotion Service:" && curl -s http://localhost:38005/health || echo "DOWN"
	@echo "Services Service:" && curl -s http://localhost:38006/health || echo "DOWN"

