<?php

namespace Tests\Feature;

use Closure;

class Whelp
{
    public static function hereWeGo(Closure $closureThatHasBagOfHolding): array
    {
        $wrappedClosure = fn() => $closureThatHasBagOfHolding();
        $memoizedWrappedClosure = fn() => once($wrappedClosure);
        return [
            $memoizedWrappedClosure
        ];
    }
}
