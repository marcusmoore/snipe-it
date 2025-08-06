<?php

use App\Models\Labels\DefaultLabel;

test('handles zero values for columns gracefully', function () {
    $this->settings->set([
        'labels_width' => 0.00000,
        'labels_display_sgutter' => 0.00000,
    ]);

    // simply ensuring constructor didn't throw exception...
    expect(new DefaultLabel())->toBeInstanceOf(DefaultLabel::class);
});

test('handles zero values for rows gracefully', function () {
    $this->settings->set([
        'labels_height' => 0.00000,
        'labels_display_bgutter' => 0.00000,
    ]);

    // simply ensuring constructor didn't throw exception...
    expect(new DefaultLabel())->toBeInstanceOf(DefaultLabel::class);
});
