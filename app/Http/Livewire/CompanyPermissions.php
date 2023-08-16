<?php

namespace App\Http\Livewire;

use App\Models\Company;
use App\Models\Group;
use Livewire\Component;

class CompanyPermissions extends Component
{
    public array $availableCompanies;

    public $groups;

    public $selectedCompany;

    public array $selectedCompanies = [];

    public function mount()
    {
        $this->availableCompanies = Company::all()->sortBy('name')->values()->toArray();
        $this->groups = Group::all()->toArray();
    }

    public function updatedSelectedCompany($value)
    {
        $selectedCompany = collect($this->availableCompanies)->first(fn($company) => $company['name'] === $value);

        $this->selectedCompanies[] = $selectedCompany;
        $this->availableCompanies = collect($this->availableCompanies)->reject($selectedCompany)->toArray();
        $this->selectedCompany = null;
    }

    public function removeCompany($id)
    {
        $company = collect($this->selectedCompanies)->first(fn($company) => $company['id'] === (int)$id);

        $this->selectedCompanies = collect($this->selectedCompanies)->reject($company)->toArray();
        $this->availableCompanies = collect($this->availableCompanies)->push($company)->sortBy('name')->values()->toArray();
    }
}
