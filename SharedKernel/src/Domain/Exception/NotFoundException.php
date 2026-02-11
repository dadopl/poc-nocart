<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Exception;

class NotFoundException extends DomainException
{
    public function __construct(string $resource, string $id)
    {
        parent::__construct(
            message: sprintf('%s with ID "%s" not found', $resource, $id),
            errorCode: 'NOT_FOUND',
        );
    }
}

