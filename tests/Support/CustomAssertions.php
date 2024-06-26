<?php

namespace Tests\Support;

use Closure;

trait CustomAssertions
{
    public function checkAssertionsFromProvider(Closure|array $data)
    {
        if (is_array($data)) {
            $data = $data['assertions'];
        }

        $data->bindTo($this)();
    }
}
