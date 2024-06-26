<?php

namespace Tests\Support;

use Closure;

trait CustomAssertions
{
    public function checkAssertionsFromProvider(Closure|array $data)
    {
        // @todo: sync with checkAssertionsFromProvider in TestResponse macros

        if (is_array($data)) {
            $data = $data['assertions'];
        }

        $data->bindTo($this)();
    }
}
