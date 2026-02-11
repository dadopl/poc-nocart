#!/bin/bash

set -e

echo "=========================================="
echo "  NOCART E2E Test Runner"
echo "=========================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Default values
CART_SERVICE_URL=${CART_SERVICE_URL:-http://cart-nginx}
SHIPPING_SERVICE_URL=${SHIPPING_SERVICE_URL:-http://shipping-nginx}
PAYMENT_SERVICE_URL=${PAYMENT_SERVICE_URL:-http://payment-nginx}
CHECKOUT_SERVICE_URL=${CHECKOUT_SERVICE_URL:-http://checkout-nginx}
PROMOTION_SERVICE_URL=${PROMOTION_SERVICE_URL:-http://promotion-nginx}
SERVICES_SERVICE_URL=${SERVICES_SERVICE_URL:-http://services-nginx}
KAFKA_SYNC_WAIT_MS=${KAFKA_SYNC_WAIT_MS:-500}

# Export env variables
export CART_SERVICE_URL
export SHIPPING_SERVICE_URL
export PAYMENT_SERVICE_URL
export CHECKOUT_SERVICE_URL
export PROMOTION_SERVICE_URL
export SERVICES_SERVICE_URL
export KAFKA_SYNC_WAIT_MS

echo ""
echo "Service URLs:"
echo "  Cart:      $CART_SERVICE_URL"
echo "  Shipping:  $SHIPPING_SERVICE_URL"
echo "  Payment:   $PAYMENT_SERVICE_URL"
echo "  Checkout:  $CHECKOUT_SERVICE_URL"
echo "  Promotion: $PROMOTION_SERVICE_URL"
echo "  Services:  $SERVICES_SERVICE_URL"
echo ""

# Check if services are up
echo "Checking service health..."

check_health() {
    local url=$1
    local name=$2
    local response=$(curl -s -o /dev/null -w "%{http_code}" "$url/health" 2>/dev/null || echo "000")

    if [ "$response" == "200" ]; then
        echo -e "  ${GREEN}✓${NC} $name is healthy"
        return 0
    else
        echo -e "  ${RED}✗${NC} $name is not responding (HTTP $response)"
        return 1
    fi
}

all_healthy=true

check_health "$CART_SERVICE_URL/health" "Cart Service" || all_healthy=false
check_health "$SHIPPING_SERVICE_URL/health" "Shipping Service" || all_healthy=false
check_health "$PAYMENT_SERVICE_URL/health" "Payment Service" || all_healthy=false
check_health "$CHECKOUT_SERVICE_URL/health" "Checkout Service" || all_healthy=false
check_health "$PROMOTION_SERVICE_URL/health" "Promotion Service" || all_healthy=false
check_health "$SERVICES_SERVICE_URL/health" "Services Service" || all_healthy=false

echo ""

if [ "$all_healthy" = false ]; then
    echo -e "${YELLOW}Warning: Some services are not healthy. Tests may fail.${NC}"
    echo ""
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Run tests
echo "Running E2E tests..."
echo ""

cd "$(dirname "$0")"

if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install --no-interaction
    echo ""
fi

# Run PHPUnit
if [ "$1" == "--filter" ] && [ -n "$2" ]; then
    ./vendor/bin/phpunit --filter="$2" --colors=always
elif [ "$1" == "--health" ]; then
    ./vendor/bin/phpunit --filter="HealthCheckTest" --colors=always
elif [ "$1" == "--flow" ]; then
    ./vendor/bin/phpunit --filter="testCompleteCheckoutFlow" --colors=always
else
    ./vendor/bin/phpunit --testsuite=E2E --colors=always
fi

echo ""
echo "=========================================="
echo -e "  ${GREEN}E2E Tests Completed${NC}"
echo "=========================================="

