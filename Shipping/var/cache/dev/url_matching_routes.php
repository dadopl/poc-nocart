<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/shipping/available' => [[['_route' => 'shipping_available', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::getAvailable'], null, ['GET' => 0], null, false, false, null]],
        '/shipping/method' => [[['_route' => 'shipping_select_method', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::selectMethod'], null, ['POST' => 0], null, false, false, null]],
        '/shipping/select' => [[['_route' => 'shipping_select', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::selectMethod'], null, ['POST' => 0], null, false, false, null]],
        '/shipping/address' => [[['_route' => 'shipping_set_address', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::setAddress'], null, ['POST' => 0], null, false, false, null]],
        '/shipping/session' => [[['_route' => 'shipping_session', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::getSession'], null, ['GET' => 0], null, false, false, null]],
        '/shipping/set-date' => [[['_route' => 'shipping_set_date', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::setDeliveryDate'], null, ['POST' => 0], null, false, false, null]],
        '/shipping/health' => [[['_route' => 'shipping_health', '_controller' => 'Nocart\\Shipping\\Ports\\Http\\ShippingController::health'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
