<?php

declare(strict_types=1);

namespace Nocart\E2E\Tests\Functional;

use Nocart\E2E\Client\CartClient;
use Nocart\E2E\Client\CheckoutClient;
use Nocart\E2E\Client\PaymentClient;
use Nocart\E2E\Client\PromotionClient;
use Nocart\E2E\Client\ServicesClient;
use Nocart\E2E\Client\ShippingClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

abstract class E2ETestCase extends TestCase
{
    protected CartClient $cartClient;
    protected ShippingClient $shippingClient;
    protected PaymentClient $paymentClient;
    protected CheckoutClient $checkoutClient;
    protected PromotionClient $promotionClient;
    protected ServicesClient $servicesClient;

    protected string $sessionId;
    protected string $userId;
    protected int $kafkaSyncWaitMs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionId = Uuid::v7()->toRfc4122();
        $this->userId = Uuid::v7()->toRfc4122();
        $this->kafkaSyncWaitMs = (int) ($_ENV['KAFKA_SYNC_WAIT_MS'] ?? 500);

        $this->cartClient = new CartClient(
            baseUrl: $_ENV['CART_SERVICE_URL'] ?? 'http://cart-nginx',
            sessionId: $this->sessionId,
            userId: $this->userId,
        );

        $this->shippingClient = new ShippingClient(
            baseUrl: $_ENV['SHIPPING_SERVICE_URL'] ?? 'http://shipping-nginx',
            sessionId: $this->sessionId,
            userId: $this->userId,
        );

        $this->paymentClient = new PaymentClient(
            baseUrl: $_ENV['PAYMENT_SERVICE_URL'] ?? 'http://payment-nginx',
            sessionId: $this->sessionId,
            userId: $this->userId,
        );

        $this->checkoutClient = new CheckoutClient(
            baseUrl: $_ENV['CHECKOUT_SERVICE_URL'] ?? 'http://checkout-nginx',
            sessionId: $this->sessionId,
            userId: $this->userId,
        );

        $this->promotionClient = new PromotionClient(
            baseUrl: $_ENV['PROMOTION_SERVICE_URL'] ?? 'http://promotion-nginx',
            sessionId: $this->sessionId,
            userId: $this->userId,
        );

        $this->servicesClient = new ServicesClient(
            baseUrl: $_ENV['SERVICES_SERVICE_URL'] ?? 'http://services-nginx',
            sessionId: $this->sessionId,
            userId: $this->userId,
        );
    }

    protected function tearDown(): void
    {
        try {
            $this->cartClient->clearCart();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    protected function waitForKafkaSync(): void
    {
        usleep($this->kafkaSyncWaitMs * 1000);
    }

    protected function assertAllServicesHealthy(): void
    {
        $this->assertTrue($this->cartClient->health(), 'Cart service is not healthy');
        $this->assertTrue($this->shippingClient->health(), 'Shipping service is not healthy');
        $this->assertTrue($this->paymentClient->health(), 'Payment service is not healthy');
        $this->assertTrue($this->checkoutClient->health(), 'Checkout service is not healthy');
        $this->assertTrue($this->promotionClient->health(), 'Promotion service is not healthy');
        $this->assertTrue($this->servicesClient->health(), 'Services service is not healthy');
    }

    protected function assertCartItemsCount(int $expectedCount): void
    {
        $cart = $this->cartClient->getCart();
        $actualCount = count($cart['items'] ?? []);
        $this->assertSame($expectedCount, $actualCount, "Expected {$expectedCount} cart items, got {$actualCount}");
    }

    protected function assertCartTotalEquals(int $expectedCents): void
    {
        $cart = $this->cartClient->getCart();
        $actualCents = $cart['total']['amount'] ?? 0;
        $this->assertSame(
            $expectedCents,
            $actualCents,
            sprintf('Expected cart total %d cents, got %d cents', $expectedCents, $actualCents)
        );
    }

    protected function assertCheckoutTotalEquals(int $expectedCents): void
    {
        $totals = $this->checkoutClient->getTotals();
        $actualCents = $totals['totals']['grand_total']['amount'] ?? 0;
        $this->assertSame(
            $expectedCents,
            $actualCents,
            sprintf('Expected checkout total %d cents, got %d cents', $expectedCents, $actualCents)
        );
    }

    protected function assertPaymentStatus(string $expectedStatus): void
    {
        $status = $this->paymentClient->getStatus();
        $actualStatus = $status['status'] ?? 'unknown';
        $this->assertSame($expectedStatus, $actualStatus, "Expected payment status {$expectedStatus}, got {$actualStatus}");
    }

    protected function getCartItemIdByOfferId(int $offerId): ?string
    {
        $cart = $this->cartClient->getCart();
        foreach ($cart['items'] ?? [] as $item) {
            if (($item['offer_id'] ?? 0) === $offerId) {
                return $item['id'] ?? null;
            }
        }
        return null;
    }

    protected function debugOutput(string $message, mixed $data = null): void
    {
        if (getenv('E2E_DEBUG') === 'true') {
            echo "\n[DEBUG] {$message}";
            if ($data !== null) {
                echo ': ' . json_encode($data, JSON_PRETTY_PRINT);
            }
            echo "\n";
        }
    }
}

