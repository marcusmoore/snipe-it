<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Gate;

class StoreAssetRequest extends ImageUploadRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::allows('create', new Asset);
    }

    public function prepareForValidation(): void
    {
        // Guard against users passing in an array for company_id instead of an integer.
        // If the company_id is not an integer then we simply use what was
        // provided to be caught by model level validation later.
        $idForCurrentUser = is_int($this->company_id)
            ? Company::getIdForCurrentUser($this->company_id)
            : $this->company_id;

        $this->parseLastAuditDate();

        $this->parseAssignedTo();

        $this->merge([
            'asset_tag' => $this->asset_tag ?? Asset::autoincrement_asset(),
            'company_id' => $idForCurrentUser,
            // this is a workaround to avoid breaking AssetObserver down the line...
            'assigned_to' => null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $modelRules = (new Asset)->getRules();

        if (Setting::getSettings()->digit_separator === '1.234,56' && is_string($this->input('purchase_cost'))) {
            // If purchase_cost was submitted as a string with a comma separator
            // then we need to ignore the normal numeric rules.
            // Since the original rules still live on the model they will be run
            // right before saving (and after purchase_cost has been
            // converted to a float via setPurchaseCostAttribute).
            $modelRules = $this->removeNumericRulesFromPurchaseCost($modelRules);
        }

        return array_merge(
            $modelRules,
            [
                // can only have either assigned_to and assigned_type or one of assigned_user, assigned_asset, or assigned_location...
                'assigned_asset' => [
                    'nullable',
                    'exists:assets,id',
                    'prohibits:assigned_user,assigned_location,assigned_to'
                ],
                'assigned_location' => [
                    'nullable',
                    'exists:locations,id',
                    'prohibits:assigned_user,assigned_asset,assigned_to'
                ],
                'assigned_user' => [
                    'nullable',
                    'exists:users,id',
                    'prohibits:assigned_asset,assigned_location,assigned_to'
                ],
            ],
            parent::rules(),
        );
    }

    public function messages()
    {
        return [
            // @todo: translate
            'assigned_asset.prohibits' => 'assigned_asset cannot be used with assigned_user, assigned_location, or assigned_to',
            'assigned_location.prohibits' => 'assigned_location cannot be used with assigned_user, assigned_asset, or assigned_to',
            'assigned_user.prohibits' => 'assigned_user cannot be used with assigned_asset, assigned_location, or assigned_to',
        ];
    }

    private function parseLastAuditDate(): void
    {
        if ($this->input('last_audit_date')) {
            try {
                $lastAuditDate = Carbon::parse($this->input('last_audit_date'));

                $this->merge([
                    'last_audit_date' => $lastAuditDate->startOfDay()->format('Y-m-d H:i:s'),
                ]);
            } catch (InvalidFormatException $e) {
                // we don't need to do anything here...
                // we'll keep the provided date in an
                // invalid format so validation picks it up later
            }
        }
    }

    private function removeNumericRulesFromPurchaseCost(array $rules): array
    {
        $purchaseCost = $rules['purchase_cost'];

        // If rule is in "|" format then turn it into an array
        if (is_string($purchaseCost)) {
            $purchaseCost = explode('|', $purchaseCost);
        }

        $rules['purchase_cost'] = array_filter($purchaseCost, function ($rule) {
            return $rule !== 'numeric' && $rule !== 'gte:0';
        });

        return $rules;
    }

    private function parseAssignedTo()
    {
        // If assigned_to is provided and assigned_type is either 'asset',
        // 'location', or 'user' we merge in the appropriate assigned_{type}
        if ($this->has('assigned_to') && in_array($this->input('assigned_type'), ['asset', 'location', 'user'])) {
            $this->merge([
                // end result: assigned_{user|asset|location} => $this->input('assigned_to')
                'assigned_' . $this->input('assigned_type') => $this->input('assigned_to'),
            ]);
        }
    }
}
