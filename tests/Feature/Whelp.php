<?php

namespace Tests\Feature;

use Closure;

class Whelp
{
    // public static function hereWeGo(Closure $closure)
    // {
    //     return $closure;
    // }
    public static function hereWeGo(Closure $closureThatHasBagOfHolding): array
    {
        $a = once(fn() => $closureThatHasBagOfHolding);

        return [
            $a,
        ];
    }
}
