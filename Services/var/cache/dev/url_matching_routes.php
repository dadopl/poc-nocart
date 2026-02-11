<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/services/available' => [[['_route' => 'services_available', '_controller' => 'Nocart\\Services\\Ports\\Http\\ServicesController::getAvailable'], null, ['GET' => 0], null, false, false, null]],
        '/services/standalone' => [[['_route' => 'services_standalone', '_controller' => 'Nocart\\Services\\Ports\\Http\\ServicesController::getStandalone'], null, ['GET' => 0], null, false, false, null]],
        '/services/select' => [[['_route' => 'services_select', '_controller' => 'Nocart\\Services\\Ports\\Http\\ServicesController::selectService'], null, ['POST' => 0], null, false, false, null]],
        '/services/session' => [[['_route' => 'services_session', '_controller' => 'Nocart\\Services\\Ports\\Http\\ServicesController::getSession'], null, ['GET' => 0], null, false, false, null]],
        '/services/health' => [[['_route' => 'services_health', '_controller' => 'Nocart\\Services\\Ports\\Http\\ServicesController::health'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/services/for\\-item/(\\d+)(*:32)'
            .')/?$}sDu',
    ],
    [ // $dynamicRoutes
        32 => [
            [['_route' => 'services_for_item', '_controller' => 'Nocart\\Services\\Ports\\Http\\ServicesController::getForItem'], ['offerId'], ['GET' => 0], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
