<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Neveljaven korak v procesu ali zaseden vir (HTTP 409, ADR-0006).
 */
class WorkflowException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  dodatni podatki za odjemalca
     */
    public function __construct(string $message, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    public function context(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), ...$this->context], 409);
    }
}
