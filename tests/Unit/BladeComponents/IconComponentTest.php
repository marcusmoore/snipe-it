<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

test('icon component does not end in newline', function () {
    $renderedTemplateString = View::make('blade.icon', ['type' => 'checkout'])->render();

    expect(Str::endsWith($renderedTemplateString, PHP_EOL))->toBeFalse('Newline found at end of icon component. Bootstrap tables will not render if there is a newline at the end of the file.');
});
