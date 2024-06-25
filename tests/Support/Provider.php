<?php

namespace Tests\Support;

use Closure;

class Provider
{
    public static function data(Closure $closureThatHasBagOfHolding): array
    {
        $wrappedClosure = fn() => $closureThatHasBagOfHolding();
        $memoizedWrappedClosure = fn() => once($wrappedClosure);

        return [
            $memoizedWrappedClosure
        ];
    }
}
