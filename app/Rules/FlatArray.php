<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates array is only one level deep.
 * Passes: ['a' => 'b']
 * Fails: ['a' => ['b', 'c']]
 */
class FlatArray implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        foreach ($value as $arrayValue) {
            if (is_array($arrayValue)) {
                $fail(":attribute cannot contain a nested data.");
            }
        }
    }
}
