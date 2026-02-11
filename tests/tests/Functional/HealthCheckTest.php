<?php

declare(strict_types=1);

namespace Nocart\E2E\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

final class HealthCheckTest extends E2ETestCase
{
    #[Test]
    public function testCartServiceIsHealthy(): void
    {
        $this->assertTrue($this->cartClient->health(), 'Cart service should be healthy');
    }

    #[Test]
    public function testShippingServiceIsHealthy(): void
    {
        $this->assertTrue($this->shippingClient->health(), 'Shipping service should be healthy');
    }

    #[Test]
    public function testPaymentServiceIsHealthy(): void
    {
        $this->assertTrue($this->paymentClient->health(), 'Payment service should be healthy');
    }

    #[Test]
    public function testCheckoutServiceIsHealthy(): void
    {
        $this->assertTrue($this->checkoutClient->health(), 'Checkout service should be healthy');
    }

    #[Test]
    public function testPromotionServiceIsHealthy(): void
    {
        $this->assertTrue($this->promotionClient->health(), 'Promotion service should be healthy');
    }

    #[Test]
    public function testServicesServiceIsHealthy(): void
    {
        $this->assertTrue($this->servicesClient->health(), 'Services service should be healthy');
    }

    #[Test]
    public function testAllServicesAreHealthy(): void
    {
        $this->assertAllServicesHealthy();
    }
}

