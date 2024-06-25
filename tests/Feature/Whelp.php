<?php

namespace Tests\Feature;

use Closure;

class Whelp
{
    public static function hereWeGo(Closure $closureThatHasBagOfHolding): array
    {
        return [
            fn() => once(fn() => $closureThatHasBagOfHolding())
        ];
    }
}
