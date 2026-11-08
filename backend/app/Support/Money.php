<?php

namespace App\Support;

/**
 * Zneski so vedno v centih (cela števila), za prikaz pa v obliki »1.260,00 €«.
 */
final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.number_format(abs($cents) / 100, 2, ',', '.').' €';
    }
}
