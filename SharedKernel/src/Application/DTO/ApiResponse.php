<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Application\DTO;

final readonly class ApiResponse
{
    private function __construct(
        public bool $success,
        /** @var array<string, mixed>|null */
        public ?array $data,
        public ?string $error,
        public int $statusCode,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function success(array $data, int $statusCode = 200): self
    {
        return new self(
            success: true,
            data: $data,
            error: null,
            statusCode: $statusCode,
        );
    }

    public static function accepted(string $message = 'Accepted'): self
    {
        return new self(
            success: true,
            data: ['message' => $message],
            error: null,
            statusCode: 202,
        );
    }

    public static function error(string $error, int $statusCode = 400): self
    {
        return new self(
            success: false,
            data: null,
            error: $error,
            statusCode: $statusCode,
        );
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, 404);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $response = ['success' => $this->success];

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        if ($this->error !== null) {
            $response['error'] = $this->error;
        }

        return $response;
    }
}

