@echo off
setlocal EnableDelayedExpansion

echo ==========================================
echo   NOCART E2E Test Runner (Windows)
echo ==========================================
echo.

REM Default values
if not defined CART_SERVICE_URL set CART_SERVICE_URL=http://localhost:8001
if not defined SHIPPING_SERVICE_URL set SHIPPING_SERVICE_URL=http://localhost:8002
if not defined PAYMENT_SERVICE_URL set PAYMENT_SERVICE_URL=http://localhost:8003
if not defined CHECKOUT_SERVICE_URL set CHECKOUT_SERVICE_URL=http://localhost:8004
if not defined PROMOTION_SERVICE_URL set PROMOTION_SERVICE_URL=http://localhost:8005
if not defined SERVICES_SERVICE_URL set SERVICES_SERVICE_URL=http://localhost:8006
if not defined KAFKA_SYNC_WAIT_MS set KAFKA_SYNC_WAIT_MS=500

echo Service URLs:
echo   Cart:      %CART_SERVICE_URL%
echo   Shipping:  %SHIPPING_SERVICE_URL%
echo   Payment:   %PAYMENT_SERVICE_URL%
echo   Checkout:  %CHECKOUT_SERVICE_URL%
echo   Promotion: %PROMOTION_SERVICE_URL%
echo   Services:  %SERVICES_SERVICE_URL%
echo.

REM Check if vendor exists
if not exist "vendor" (
    echo Installing dependencies...
    call composer install --no-interaction
    echo.
)

REM Run tests
echo Running E2E tests...
echo.

if "%1"=="--health" (
    call vendor\bin\phpunit --filter="HealthCheckTest" --colors=always
) else if "%1"=="--flow" (
    call vendor\bin\phpunit --filter="testCompleteCheckoutFlow" --colors=always
) else if "%1"=="--filter" (
    call vendor\bin\phpunit --filter="%2" --colors=always
) else (
    call vendor\bin\phpunit --testsuite=E2E --colors=always
)

echo.
echo ==========================================
echo   E2E Tests Completed
echo ==========================================

endlocal

