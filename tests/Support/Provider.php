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

    public function runAssertions(Closure $closure): void
    {
        $callingTestInstance = debug_backtrace()[1]['object'];
        $closure->bindTo($callingTestInstance)();
    }
}
