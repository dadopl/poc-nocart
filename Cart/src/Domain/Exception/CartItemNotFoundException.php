<?php

declare(strict_types=1);

namespace Nocart\Cart\Domain\Exception;

use Nocart\SharedKernel\Domain\Exception\NotFoundException;
use Nocart\SharedKernel\Domain\ValueObject\ItemId;

final class CartItemNotFoundException extends NotFoundException
{
    public static function withId(ItemId $itemId): self
    {
        return new self('CartItem', $itemId->toString());
    }
}

