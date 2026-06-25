<?php

namespace App\Models\Traits;

use App\Models\CompanyableScope;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait CompanyableTrait
{
    /**
     * This trait is used to scope models to the current company. To use this scope on companyable models,
     * we use the "use Companyable;" statement at the top of the mode.
     *
     * @see    Company::scopeCompanyables()
     *
     * @return void
     */
    public static function bootCompanyableTrait()
    {
        static::addGlobalScope(new CompanyableScope);
    }

    /**
     * The company ID this model presents for FMCS checkout validation.
     * Location overrides this to walk the parent chain.
     */
    public function effectiveFmcsCompanyId(): ?int
    {
        return $this->company_id ? (int) $this->company_id : null;
    }

    /**
     * Whether this item may be checked out to the given target under FMCS rules.
     *
     * Returns true when:
     *  - FMCS is disabled, OR
     *  - this item has no company (uncompanied items are unrestricted), OR
     *  - target is a User whose company pivot includes this item's company, OR
     *  - target is a Location and scope_locations_fmcs is disabled, OR
     *  - target has no effective company and null_company_is_floater is enabled, OR
     *  - target's effective company_id exactly matches this item's company_id.
     */
    public function canCheckoutTo(Model $target): bool
    {
        $settings = Setting::getSettings();

        if (! $settings->full_multiple_companies_support) {
            return true;
        }

        if (! $this->company_id) {
            if (is_null($target->company_id)) {
                return true;
            }

            return (bool) $settings->null_company_is_floater;
        }

        if ($target instanceof User) {
            return $target->canReceiveFromCompany((int) $this->company_id);
        }

        if ($target instanceof Location && ! $settings->scope_locations_fmcs) {
            return true;
        }

        $targetCompanyId = method_exists($target, 'effectiveFmcsCompanyId')
            ? $target->effectiveFmcsCompanyId()
            : ($target->company_id ? (int) $target->company_id : null);

        if (is_null($targetCompanyId)) {
            return (bool) $settings->null_company_is_floater;
        }

        return $targetCompanyId === (int) $this->company_id;
    }
}
