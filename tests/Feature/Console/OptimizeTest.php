<?php

test('optimize succeeds', function () {
    $this->beforeApplicationDestroyed(function () {
        $this->artisan('config:clear');
        $this->artisan('route:clear');
    });

    $this->artisan('optimize')->assertSuccessful();
});
