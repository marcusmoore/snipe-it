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

    public static function runAssertions(Closure $closure, $testInstance): void
    {
        $closure->bindTo($testInstance)();
    }
}
