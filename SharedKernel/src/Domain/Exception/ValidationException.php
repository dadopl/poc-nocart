<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Exception;

final class ValidationException extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct(
            message: $message,
            errorCode: 'VALIDATION_ERROR',
        );
    }
}

