<?php

namespace Tests\Support;

use Closure;
use ReflectionFunction;

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

    public static function setUp(Closure $something)
    {
        // @todo: should this be wrapped in a once?
        $something();
    }

    public static function share(array $data)
    {
        app()->singleton('bad_idea', function () use ($data) {
            return $data;
        });
    }

    public static function get(string $string)
    {
        return resolve('bad_idea')[$string];
    }
}
