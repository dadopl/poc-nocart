<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/health' => [[['_route' => 'promotion_health', '_controller' => 'Nocart\\Promotion\\Ports\\Http\\HealthController::health'], null, ['GET' => 0], null, false, false, null]],
        '/promotions/available' => [[['_route' => 'promotions_available', '_controller' => 'Nocart\\Promotion\\Ports\\Http\\PromotionController::getAvailable'], null, ['GET' => 0], null, false, false, null]],
        '/promotions/apply' => [[['_route' => 'promotions_apply', '_controller' => 'Nocart\\Promotion\\Ports\\Http\\PromotionController::apply'], null, ['POST' => 0], null, false, false, null]],
        '/promotions/apply-code' => [[['_route' => 'promotions_apply_code', '_controller' => 'Nocart\\Promotion\\Ports\\Http\\PromotionController::applyCode'], null, ['POST' => 0], null, false, false, null]],
        '/promotions/session' => [[['_route' => 'promotions_session', '_controller' => 'Nocart\\Promotion\\Ports\\Http\\PromotionController::getSession'], null, ['GET' => 0], null, false, false, null]],
        '/promotions/health' => [[['_route' => 'promotions_health', '_controller' => 'Nocart\\Promotion\\Ports\\Http\\PromotionController::health'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
