<?php

namespace Tests\Support;

trait CustomAssertions
{
    public function checkAssertionsFromProvider(array $data)
    {
        $data['assertions']->bindTo($this)();
    }
}
