<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/checkout/totals' => [[['_route' => 'checkout_totals', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::getTotals'], null, ['GET' => 0], null, false, false, null]],
        '/checkout/summary' => [[['_route' => 'checkout_summary', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::getSummary'], null, ['GET' => 0], null, false, false, null]],
        '/checkout/recalculate' => [[['_route' => 'checkout_recalculate', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::recalculate'], null, ['POST' => 0], null, false, false, null]],
        '/checkout/customer-data' => [[['_route' => 'checkout_customer_data', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::setCustomerData'], null, ['POST' => 0], null, false, false, null]],
        '/checkout/consents' => [[['_route' => 'checkout_consents', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::setConsents'], null, ['POST' => 0], null, false, false, null]],
        '/checkout/finalize' => [[['_route' => 'checkout_finalize', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::finalize'], null, ['POST' => 0], null, false, false, null]],
        '/checkout/complete-payment' => [[['_route' => 'checkout_complete_payment', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\CheckoutController::completePayment'], null, ['POST' => 0], null, false, false, null]],
        '/checkout/health' => [[['_route' => 'checkout_health', '_controller' => 'Nocart\\Checkout\\Ports\\Http\\HealthController::health'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
