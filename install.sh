
#!/bin/bash

set -e

echo "=========================================="
echo "  NOCART - Installation Script"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

# Determine which docker compose command to use
DOCKER_COMPOSE="docker compose"

echo -e "${BLUE}Using: $DOCKER_COMPOSE${NC}"
echo ""

echo -e "${BLUE}Building Docker images...${NC}"
$DOCKER_COMPOSE build

echo ""
echo -e "${BLUE}Installing SharedKernel dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" -w /var/www/shared cart-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing Cart Service dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" cart-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing Shipping Service dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" shipping-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing Payment Service dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" payment-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing Promotion Service dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" promotion-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing Services Service dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" services-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing Checkout Service dependencies...${NC}"
$DOCKER_COMPOSE run --rm --entrypoint "" checkout-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${BLUE}Installing E2E Tests dependencies...${NC}"
# Use cart-php container for tests (in case they need rdkafka extension)
$DOCKER_COMPOSE run --rm --entrypoint "" -v "$(pwd)/tests:/var/www/html" -w /var/www/html cart-php composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

echo ""
echo -e "${GREEN}=========================================="
echo "  All dependencies installed!"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Start Docker stack: ${BLUE}$DOCKER_COMPOSE up -d${NC}"
echo "  2. Wait for health checks: ${BLUE}sleep 30${NC}"
echo "  3. Initialize Kafka topics:"
echo "     ${BLUE}$DOCKER_COMPOSE exec redpanda rpk topic create cart-events shipping-events payment-events promotion-events services-events checkout-events${NC}"
echo "  4. Run E2E tests: ${BLUE}cd tests && ./run-e2e.sh${NC}"
echo ""
echo -e "${GREEN}=========================================="
echo "  SERVICE URLS"
echo "==========================================${NC}"
echo ""
echo -e "${BLUE}HTTP APIs:${NC}"
echo "  Cart Service:      http://localhost:38001"
echo "  Shipping Service:  http://localhost:38002"
echo "  Payment Service:   http://localhost:38003"
echo "  Checkout Service:  http://localhost:38004"
echo "  Promotion Service: http://localhost:38005"
echo "  Services Service:  http://localhost:38006"
echo ""
echo -e "${BLUE}Infrastructure:${NC}"
echo "  Redis:             localhost:36379"
echo "  Kafka (external):  localhost:39092"
echo "  Kafka UI:          http://localhost:38080"
echo "  Schema Registry:   http://localhost:38081"
echo "  Pandaproxy:        http://localhost:38082"
echo ""
echo -e "${GREEN}Tip: You can rebuild and reinstall anytime by running: ./install.sh${NC}"
echo ""

