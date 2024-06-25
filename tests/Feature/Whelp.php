<?php

namespace Tests\Feature;

use Closure;

class Whelp
{
    public static function hereWeGo(Closure $closure)
    {
        return $closure;
    }
}
