<?php

namespace App\Services;

use App\Enums\ChargeType;
use App\Models\Charge;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Izvor obveznosti: položnica z izračunom deleža, rezervacija ali prijava okvare (US-04, #105).
 * Vrne samo podatke, ki se nanašajo na najemnika te obveznosti.
 */
class ChargeSourceResolver
{
    /** @var array<string, callable(Charge): ?array> */
    private array $resolvers = [];

    public function register(ChargeType $type, callable $resolver): void
    {
        $this->resolvers[$type->value] = $resolver;
    }

    /**
     * @return array{type: string, data: array}
     */
    public function resolve(Charge $charge): array
    {
        if (! $charge->hasSource()) {
            throw new NotFoundHttpException('Ta obveznost nima povezanega izvora.');
        }

        $resolver = $this->resolvers[$charge->type->value] ?? null;
        $data = $resolver ? $resolver($charge) : null;

        // Manjkajoč izvor je napaka, ne prazna stran.
        if ($data === null) {
            throw new NotFoundHttpException('Izvor obveznosti ni bil najden.');
        }

        return ['type' => $charge->type->value, 'data' => $data];
    }
}
