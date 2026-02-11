<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\ValueObject;

enum ItemType: string
{
    case PRODUCT = 'product';
    case WARRANTY = 'warranty';
    case ACCESSORY = 'accessory';
    case SERVICE_ITEM = 'service_item';
    case SERVICE_STANDALONE = 'service_standalone';
    case SERVICE_SHIPPING = 'service_shipping';

    public function isProduct(): bool
    {
        return $this === self::PRODUCT;
    }

    public function isService(): bool
    {
        return in_array($this, [self::SERVICE_ITEM, self::SERVICE_STANDALONE, self::SERVICE_SHIPPING], true);
    }

    public function isChildItem(): bool
    {
        return in_array($this, [self::WARRANTY, self::ACCESSORY, self::SERVICE_ITEM], true);
    }
}

