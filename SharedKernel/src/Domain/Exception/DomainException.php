<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Exception;

abstract class DomainException extends \Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

