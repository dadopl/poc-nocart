<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/cart' => [
            [['_route' => 'cart_get', '_controller' => 'Nocart\\Cart\\Ports\\Http\\CartController::getCart'], null, ['GET' => 0], null, false, false, null],
            [['_route' => 'cart_clear', '_controller' => 'Nocart\\Cart\\Ports\\Http\\CartController::clearCart'], null, ['DELETE' => 0], null, false, false, null],
        ],
        '/cart/items' => [[['_route' => 'cart_add_item', '_controller' => 'Nocart\\Cart\\Ports\\Http\\CartController::addItem'], null, ['POST' => 0], null, false, false, null]],
        '/cart/health' => [[['_route' => 'cart_health', '_controller' => 'Nocart\\Cart\\Ports\\Http\\CartController::health'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/cart/items/([^/]++)(?'
                    .'|(*:30)'
                .')'
            .')/?$}sDu',
    ],
    [ // $dynamicRoutes
        30 => [
            [['_route' => 'cart_remove_item', '_controller' => 'Nocart\\Cart\\Ports\\Http\\CartController::removeItem'], ['itemId'], ['DELETE' => 0], null, false, true, null],
            [['_route' => 'cart_change_quantity', '_controller' => 'Nocart\\Cart\\Ports\\Http\\CartController::changeQuantity'], ['itemId'], ['PATCH' => 0], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
