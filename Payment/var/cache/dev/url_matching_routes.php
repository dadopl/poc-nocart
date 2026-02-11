<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/payment/available' => [[['_route' => 'payment_available', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::getAvailable'], null, ['GET' => 0], null, false, false, null]],
        '/payment/method' => [[['_route' => 'payment_select_method', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::selectMethod'], null, ['POST' => 0], null, false, false, null]],
        '/payment/select' => [[['_route' => 'payment_select', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::selectMethod'], null, ['POST' => 0], null, false, false, null]],
        '/payment/status' => [[['_route' => 'payment_status', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::getStatus'], null, ['GET' => 0], null, false, false, null]],
        '/payment/initialize' => [[['_route' => 'payment_initialize', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::initialize'], null, ['POST' => 0], null, false, false, null]],
        '/payment/confirm' => [[['_route' => 'payment_confirm', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::confirm'], null, ['POST' => 0], null, false, false, null]],
        '/payment/health' => [[['_route' => 'payment_health', '_controller' => 'Nocart\\Payment\\Ports\\Http\\PaymentController::health'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
