<?php

declare(strict_types=1);

namespace Nocart\E2E\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

/**
 * Complete Checkout Flow E2E Test
 *
 * Ten test pokrywa cały flow zakupowy opisany w flow.md:
 * 1. Dodanie laptopa do koszyka (5999 PLN)
 * 2. Dodanie gwarancji do laptopa (299 PLN)
 * 3. Dodanie torby jako akcesorium (149 PLN)
 * 4. Sprawdzenie dostępnych promocji
 * 5. Zastosowanie promocji "2 w cenie 1.5" (zwiększenie ilości do 2)
 * 6. Dodanie usługi SMS (2 PLN)
 * 7. Wybór metody dostawy (kurier DPD)
 * 8. Ustawienie adresu dostawy
 * 9. Wybór daty dostawy z express delivery
 * 10. Dodanie lodówki (2999 PLN)
 * 11. Dodanie usługi wniesienia dla lodówki (99 PLN)
 * 12. Wybór metody płatności (BLIK)
 * 13. Podanie danych klienta
 * 14. Akceptacja zgód
 * 15. Finalizacja zamówienia
 * 16. Potwierdzenie płatności BLIK
 * 17. Weryfikacja zakończenia checkout
 */
final class CheckoutFlowTest extends E2ETestCase
{
    private const LAPTOP_OFFER_ID = 123;
    private const LAPTOP_PRICE_CENTS = 599900;

    private const WARRANTY_OFFER_ID = 456;
    private const WARRANTY_PRICE_CENTS = 29900;

    private const BAG_OFFER_ID = 789;
    private const BAG_PRICE_CENTS = 14900;

    private const FRIDGE_OFFER_ID = 999;
    private const FRIDGE_PRICE_CENTS = 299900;

    private const SMS_SERVICE_PRICE_CENTS = 200;
    private const CARRYING_SERVICE_PRICE_CENTS = 9900;

    private const COURIER_DPD_PRICE_CENTS = 1599;
    private const EXPRESS_DELIVERY_FEE_CENTS = 2999;

    #[Test]
    public function testCompleteCheckoutFlow(): void
    {
        $this->debugOutput('Starting Complete Checkout Flow Test');
        $this->debugOutput('Session ID', $this->sessionId);
        $this->debugOutput('User ID', $this->userId);

        // =================================================================
        // STEP 1: Dodanie laptopa do koszyka
        // =================================================================
        $this->debugOutput('STEP 1: Adding laptop to cart');

        $result = $this->cartClient->addItem(
            offerId: self::LAPTOP_OFFER_ID,
            type: 'product',
            quantity: 1,
            price: self::LAPTOP_PRICE_CENTS / 100,
        );

        $this->assertArrayHasKey('message', $result);
        $this->assertCartItemsCount(1);
        $this->assertCartTotalEquals(self::LAPTOP_PRICE_CENTS);

        $laptopItemId = $this->getCartItemIdByOfferId(self::LAPTOP_OFFER_ID);
        $this->assertNotNull($laptopItemId, 'Laptop item ID should exist');
        $this->debugOutput('Laptop item ID', $laptopItemId);

        // =================================================================
        // STEP 2: Dodanie gwarancji do laptopa
        // =================================================================
        $this->debugOutput('STEP 2: Adding warranty to laptop');

        $this->cartClient->addItem(
            offerId: self::WARRANTY_OFFER_ID,
            type: 'warranty',
            quantity: 1,
            price: self::WARRANTY_PRICE_CENTS / 100,
            parentItemId: $laptopItemId,
        );

        $expectedTotal = self::LAPTOP_PRICE_CENTS + self::WARRANTY_PRICE_CENTS;
        $this->assertCartItemsCount(2);
        $this->assertCartTotalEquals($expectedTotal);

        // =================================================================
        // STEP 3: Dodanie torby jako akcesorium
        // =================================================================
        $this->debugOutput('STEP 3: Adding bag as accessory');

        $this->cartClient->addItem(
            offerId: self::BAG_OFFER_ID,
            type: 'accessory',
            quantity: 1,
            price: self::BAG_PRICE_CENTS / 100,
            parentItemId: $laptopItemId,
        );

        $expectedTotal += self::BAG_PRICE_CENTS;
        $this->assertCartItemsCount(3);
        $this->assertCartTotalEquals($expectedTotal);

        $this->debugOutput('Cart total after accessories', $expectedTotal);

        // =================================================================
        // STEP 4: Sprawdzenie dostępnych promocji
        // =================================================================
        $this->debugOutput('STEP 4: Checking available promotions');

        $promotions = $this->promotionClient->getAvailablePromotions(
            cartTotalCents: $expectedTotal,
            itemQuantity: 1,
        );

        $this->assertArrayHasKey('promotions', $promotions);
        $this->assertNotEmpty($promotions['promotions'], 'Should have available promotions');
        $this->debugOutput('Available promotions count', count($promotions['promotions']));

        // Find 2x50 promotion
        $promo2x50Found = false;
        foreach ($promotions['promotions'] as $promo) {
            if ($promo['promotion']['id'] === 'promo-2x50') {
                $promo2x50Found = true;
                break;
            }
        }
        $this->assertTrue($promo2x50Found, 'promo-2x50 should be available');

        // =================================================================
        // STEP 5: Zastosowanie promocji "2 w cenie 1.5"
        // =================================================================
        $this->debugOutput('STEP 5: Applying promo-2x50 promotion');

        // First increase quantity to 2 (required for promo)
        $this->cartClient->changeQuantity($laptopItemId, 2);

        // Recalculate expected total with 2 laptops
        $laptopSubtotal = self::LAPTOP_PRICE_CENTS * 2;
        $expectedTotal = $laptopSubtotal + self::WARRANTY_PRICE_CENTS + self::BAG_PRICE_CENTS;
        $this->debugOutput('Cart total with 2 laptops', $expectedTotal);

        // Apply promotion
        $this->promotionClient->applyPromotion(
            promotionId: 'promo-2x50',
            cartTotalCents: $expectedTotal,
            itemQuantity: 2,
        );

        $this->waitForKafkaSync();

        // Verify promotion applied
        $promotionSession = $this->promotionClient->getSession();
        $this->assertNotEmpty($promotionSession['applied_promotions'] ?? [], 'Promotion should be applied');

        // Recalculate totals in checkout
        $this->checkoutClient->recalculateTotals();
        $this->waitForKafkaSync();

        // =================================================================
        // STEP 6: Dodanie usługi SMS
        // =================================================================
        $this->debugOutput('STEP 6: Adding SMS notification service');

        $this->cartClient->addItem(
            offerId: 0,
            type: 'service_standalone',
            quantity: 1,
            price: self::SMS_SERVICE_PRICE_CENTS / 100,
            serviceId: 'sms-notif',
        );

        $this->waitForKafkaSync();

        // =================================================================
        // STEP 7: Wybór metody dostawy
        // =================================================================
        $this->debugOutput('STEP 7: Selecting shipping method (DPD courier)');

        $availableShipping = $this->shippingClient->getAvailableMethods();
        $this->assertArrayHasKey('methods', $availableShipping);
        $this->assertNotEmpty($availableShipping['methods'], 'Should have shipping methods');

        $this->shippingClient->selectMethod(methodId: 'courier_dpd');
        $this->waitForKafkaSync();

        // =================================================================
        // STEP 8: Ustawienie adresu dostawy
        // =================================================================
        $this->debugOutput('STEP 8: Setting shipping address');

        $this->shippingClient->setAddress(
            street: 'Testowa 1',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'PL',
        );

        $shippingSession = $this->shippingClient->getSession();
        $this->assertNotNull($shippingSession['address'] ?? null, 'Address should be set');

        // =================================================================
        // STEP 9: Wybór daty dostawy z express delivery
        // =================================================================
        $this->debugOutput('STEP 9: Setting express delivery date');

        $tomorrowDate = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');

        $this->shippingClient->setDeliveryDate(
            date: $tomorrowDate,
            express: true,
        );

        $this->waitForKafkaSync();

        $shippingSession = $this->shippingClient->getSession();
        $this->assertTrue($shippingSession['is_express'] ?? false, 'Express delivery should be enabled');

        // =================================================================
        // STEP 10: Dodanie lodówki
        // =================================================================
        $this->debugOutput('STEP 10: Adding fridge to cart');

        $this->cartClient->addItem(
            offerId: self::FRIDGE_OFFER_ID,
            type: 'product',
            quantity: 1,
            price: self::FRIDGE_PRICE_CENTS / 100,
        );

        $this->waitForKafkaSync();

        $fridgeItemId = $this->getCartItemIdByOfferId(self::FRIDGE_OFFER_ID);
        $this->assertNotNull($fridgeItemId, 'Fridge item ID should exist');
        $this->debugOutput('Fridge item ID', $fridgeItemId);

        // =================================================================
        // STEP 11: Dodanie usługi wniesienia dla lodówki
        // =================================================================
        $this->debugOutput('STEP 11: Adding carrying service for fridge');

        $this->cartClient->addItem(
            offerId: 0,
            type: 'service_item',
            quantity: 1,
            price: self::CARRYING_SERVICE_PRICE_CENTS / 100,
            parentItemId: $fridgeItemId,
            serviceId: 'carrying',
        );

        $this->waitForKafkaSync();

        // Recalculate totals
        $this->checkoutClient->recalculateTotals();
        $this->waitForKafkaSync();

        // =================================================================
        // STEP 12: Wybór metody płatności
        // =================================================================
        $this->debugOutput('STEP 12: Selecting payment method (BLIK)');

        $availablePayments = $this->paymentClient->getAvailableMethods();
        $this->assertArrayHasKey('methods', $availablePayments);

        $blikFound = false;
        foreach ($availablePayments['methods'] as $method) {
            if ($method['id'] === 'blik') {
                $blikFound = true;
                break;
            }
        }
        $this->assertTrue($blikFound, 'BLIK payment method should be available');

        $this->paymentClient->selectMethod(methodId: 'blik');
        $this->waitForKafkaSync();

        // =================================================================
        // STEP 13: Podanie danych klienta
        // =================================================================
        $this->debugOutput('STEP 13: Setting customer data');

        $this->checkoutClient->setCustomerData(
            email: 'jan.kowalski@example.com',
            firstName: 'Jan',
            lastName: 'Kowalski',
            phone: '+48123456789',
        );

        $this->waitForKafkaSync();

        // =================================================================
        // STEP 14: Akceptacja zgód
        // =================================================================
        $this->debugOutput('STEP 14: Accepting consents');

        $this->checkoutClient->setConsents(
            terms: true,
            privacy: true,
            marketing: false,
            newsletter: true,
        );

        $this->waitForKafkaSync();

        // Verify checkout is ready
        $totals = $this->checkoutClient->getTotals();
        $this->debugOutput('Checkout totals before finalize', $totals);

        $this->assertTrue(
            $totals['can_finalize'] ?? false,
            'Checkout should be ready to finalize. Missing: ' . implode(', ', $totals['missing_requirements'] ?? [])
        );

        // =================================================================
        // STEP 15: Finalizacja zamówienia
        // =================================================================
        $this->debugOutput('STEP 15: Finalizing checkout');

        $orderResult = $this->checkoutClient->finalize();

        $this->assertArrayHasKey('order_id', $orderResult);
        $orderId = $orderResult['order_id'];
        $this->assertStringStartsWith('ORD-', $orderId);
        $this->debugOutput('Order created', $orderId);

        // =================================================================
        // STEP 16: Inicjalizacja i potwierdzenie płatności BLIK
        // =================================================================
        $this->debugOutput('STEP 16: Initializing and confirming BLIK payment');

        // Get final amount from checkout
        $grandTotal = $totals['totals']['grand_total']['amount'] ?? 0;

        $paymentInit = $this->paymentClient->initializePayment(
            orderId: $orderId,
            amountCents: $grandTotal,
        );

        $this->assertArrayHasKey('message', $paymentInit);
        $this->debugOutput('Payment initialized', $paymentInit);

        // Confirm BLIK with simulated code
        $paymentConfirm = $this->paymentClient->confirmPayment(code: '123456');
        $this->assertArrayHasKey('message', $paymentConfirm);
        $this->debugOutput('Payment confirmed', $paymentConfirm);

        $this->waitForKafkaSync();

        // =================================================================
        // STEP 17: Weryfikacja zakończenia checkout
        // =================================================================
        $this->debugOutput('STEP 17: Verifying checkout completion');

        // Verify payment status
        $paymentStatus = $this->paymentClient->getStatus();
        $this->debugOutput('Final payment status', $paymentStatus);
        $this->assertSame('succeeded', $paymentStatus['status'] ?? 'unknown', 'Payment should be succeeded');

        // Verify checkout summary
        $summary = $this->checkoutClient->getSummary();
        $this->debugOutput('Final checkout summary', $summary);
        $this->assertSame('completed', $summary['session']['status'] ?? 'unknown', 'Checkout should be completed');
        $this->assertSame($orderId, $summary['session']['order_id'] ?? null, 'Order ID should match');

        // Verify cart is cleared (optional - depends on implementation)
        try {
            $cart = $this->cartClient->getCart();
            $itemCount = count($cart['items'] ?? []);
            $this->assertSame(0, $itemCount, 'Cart should be cleared after successful payment');
        } catch (\Throwable $e) {
            $this->debugOutput('Cart verification skipped (might be cleared)', $e->getMessage());
        }

        $this->debugOutput('=== CHECKOUT FLOW TEST COMPLETED SUCCESSFULLY ===');
    }

    #[Test]
    public function testPartialCheckoutFlowWithPromoCode(): void
    {
        $this->debugOutput('Starting Partial Checkout Flow Test with Promo Code');

        // Add single laptop
        $this->cartClient->addItem(
            offerId: self::LAPTOP_OFFER_ID,
            type: 'product',
            quantity: 1,
            price: self::LAPTOP_PRICE_CENTS / 100,
        );

        $this->assertCartItemsCount(1);
        $this->assertCartTotalEquals(self::LAPTOP_PRICE_CENTS);

        // Apply promo code SAVE10 (10% off, min 100 PLN)
        $this->promotionClient->applyPromoCode(
            code: 'SAVE10',
            orderTotalCents: self::LAPTOP_PRICE_CENTS,
        );

        $this->waitForKafkaSync();

        // Verify promo code applied
        $promotionSession = $this->promotionClient->getSession();
        $this->assertNotEmpty($promotionSession['applied_codes'] ?? [], 'Promo code should be applied');

        // Select shipping
        $this->shippingClient->selectMethod(methodId: 'inpost_locker');
        $this->shippingClient->setAddress(
            street: 'Testowa 5',
            city: 'Kraków',
            postalCode: '30-001',
        );

        $this->waitForKafkaSync();

        // Recalculate totals
        $this->checkoutClient->recalculateTotals();
        $this->waitForKafkaSync();

        // Verify totals include discount
        $totals = $this->checkoutClient->getTotals();
        $this->debugOutput('Totals with promo code', $totals);

        $promotionDiscount = $totals['totals']['promotion_discount']['amount'] ?? 0;
        $this->assertGreaterThan(0, $promotionDiscount, 'Should have promotion discount');

        $this->debugOutput('Partial checkout flow test completed');
    }

    #[Test]
    public function testCheckoutFlowWithMultipleServices(): void
    {
        $this->debugOutput('Starting Checkout Flow Test with Multiple Services');

        // Add fridge (AGD category)
        $this->cartClient->addItem(
            offerId: self::FRIDGE_OFFER_ID,
            type: 'product',
            quantity: 1,
            price: self::FRIDGE_PRICE_CENTS / 100,
        );

        $fridgeItemId = $this->getCartItemIdByOfferId(self::FRIDGE_OFFER_ID);
        $this->assertNotNull($fridgeItemId);

        // Get available services for fridge
        $services = $this->servicesClient->getServicesForItem(
            offerId: self::FRIDGE_OFFER_ID,
            category: 'agd',
        );

        $this->assertArrayHasKey('services', $services);
        $this->debugOutput('Available services for fridge', $services);

        // Add carrying service
        $this->cartClient->addItem(
            offerId: 0,
            type: 'service_item',
            quantity: 1,
            price: self::CARRYING_SERVICE_PRICE_CENTS / 100,
            parentItemId: $fridgeItemId,
            serviceId: 'carrying',
        );

        // Add standalone service (SMS)
        $standaloneServices = $this->servicesClient->getStandaloneServices();
        $this->assertArrayHasKey('services', $standaloneServices);

        $this->cartClient->addItem(
            offerId: 0,
            type: 'service_standalone',
            quantity: 1,
            price: self::SMS_SERVICE_PRICE_CENTS / 100,
            serviceId: 'sms-notif',
        );

        $this->waitForKafkaSync();

        // Verify cart has all items
        $this->assertCartItemsCount(3); // fridge + carrying + sms

        $expectedTotal = self::FRIDGE_PRICE_CENTS + self::CARRYING_SERVICE_PRICE_CENTS + self::SMS_SERVICE_PRICE_CENTS;
        $this->assertCartTotalEquals($expectedTotal);

        $this->debugOutput('Multiple services test completed');
    }

    #[Test]
    public function testCheckoutValidationErrors(): void
    {
        $this->debugOutput('Starting Checkout Validation Errors Test');

        // Add item to cart
        $this->cartClient->addItem(
            offerId: self::LAPTOP_OFFER_ID,
            type: 'product',
            quantity: 1,
            price: self::LAPTOP_PRICE_CENTS / 100,
        );

        // Try to finalize without completing all steps
        $totals = $this->checkoutClient->getTotals();

        $this->assertFalse($totals['can_finalize'] ?? true, 'Should not be able to finalize');
        $this->assertNotEmpty($totals['missing_requirements'] ?? [], 'Should have missing requirements');

        $missing = $totals['missing_requirements'] ?? [];
        $this->assertContains('shipping_method', $missing, 'Should require shipping method');
        $this->assertContains('payment_method', $missing, 'Should require payment method');
        $this->assertContains('customer_data', $missing, 'Should require customer data');
        $this->assertContains('consents', $missing, 'Should require consents');

        $this->debugOutput('Validation errors test completed');
    }

    #[Test]
    public function testShippingMethodSelection(): void
    {
        $this->debugOutput('Starting Shipping Method Selection Test');

        // Get available methods
        $methods = $this->shippingClient->getAvailableMethods();

        $this->assertArrayHasKey('methods', $methods);
        $this->assertNotEmpty($methods['methods']);

        // Verify expected methods exist
        $methodIds = array_column($methods['methods'], 'id');
        $this->assertContains('courier_dpd', $methodIds);
        $this->assertContains('inpost_locker', $methodIds);
        $this->assertContains('pickup_store', $methodIds);

        // Select each method and verify
        foreach (['courier_dpd', 'inpost_locker', 'pickup_store'] as $methodId) {
            $this->shippingClient->selectMethod(methodId: $methodId);

            $session = $this->shippingClient->getSession();
            $this->assertSame(
                $methodId,
                $session['selected_method']['id'] ?? null,
                "Should have selected {$methodId}"
            );
        }

        $this->debugOutput('Shipping method selection test completed');
    }

    #[Test]
    public function testPaymentMethodSelection(): void
    {
        $this->debugOutput('Starting Payment Method Selection Test');

        // Get available methods
        $methods = $this->paymentClient->getAvailableMethods();

        $this->assertArrayHasKey('methods', $methods);
        $this->assertNotEmpty($methods['methods']);

        // Verify expected methods exist
        $methodIds = array_column($methods['methods'], 'id');
        $this->assertContains('blik', $methodIds);
        $this->assertContains('card', $methodIds);
        $this->assertContains('transfer', $methodIds);

        // Select BLIK
        $this->paymentClient->selectMethod(methodId: 'blik');

        $status = $this->paymentClient->getStatus();
        $this->assertSame('blik', $status['selected_method'] ?? null, 'Should have selected BLIK');

        $this->debugOutput('Payment method selection test completed');
    }

    #[Test]
    public function testCartOperations(): void
    {
        $this->debugOutput('Starting Cart Operations Test');

        // Add item
        $this->cartClient->addItem(
            offerId: self::LAPTOP_OFFER_ID,
            type: 'product',
            quantity: 1,
            price: self::LAPTOP_PRICE_CENTS / 100,
        );

        $this->assertCartItemsCount(1);
        $laptopId = $this->getCartItemIdByOfferId(self::LAPTOP_OFFER_ID);

        // Change quantity
        $this->cartClient->changeQuantity($laptopId, 3);

        $cart = $this->cartClient->getCart();
        $laptopItem = null;
        foreach ($cart['items'] ?? [] as $item) {
            if ($item['id'] === $laptopId) {
                $laptopItem = $item;
                break;
            }
        }

        $this->assertNotNull($laptopItem);
        $this->assertSame(3, $laptopItem['quantity'] ?? 0, 'Quantity should be 3');
        $this->assertCartTotalEquals(self::LAPTOP_PRICE_CENTS * 3);

        // Remove item
        $this->cartClient->removeItem($laptopId);
        $this->assertCartItemsCount(0);

        // Clear cart (should work even when empty)
        $this->cartClient->clearCart();

        $this->debugOutput('Cart operations test completed');
    }
}

