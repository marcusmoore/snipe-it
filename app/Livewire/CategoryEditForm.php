<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class CategoryEditForm extends Component
{
    public $defaultEulaText;

    public $eulaText;

    public $originalSendCheckInEmailValue;

    public $requireAcceptance;

    public $sendCheckInEmail;

    public $useDefaultEula;

    public function mount()
    {
        $this->originalSendCheckInEmailValue = $this->sendCheckInEmail;

        if ($this->eulaText || $this->useDefaultEula) {
            $this->sendCheckInEmail = 1;
        }
    }

    public function render()
    {
        return view('livewire.category-edit-form');
    }

    public function updated($property, $value)
    {
        if (! in_array($property, ['eulaText', 'useDefaultEula'])) {
            return;
        }

        $this->sendCheckInEmail = $this->eulaText || $this->useDefaultEula ? 1 : $this->originalSendCheckInEmailValue;
    }

    #[Computed]
    public function shouldDisplayEmailMessage(): bool
    {
        return $this->eulaText || $this->useDefaultEula;
    }

    #[Computed]
    public function emailMessage(): string
    {
        if ($this->useDefaultEula) {
            return trans('admin/categories/general.email_will_be_sent_due_to_global_eula');
        }

        return trans('admin/categories/general.email_will_be_sent_due_to_category_eula');
    }

    #[Computed]
    public function eulaTextDisabled()
    {
        return (bool)$this->useDefaultEula;
    }

    #[Computed]
    public function sendCheckInEmailDisabled()
    {
        return $this->eulaText || $this->useDefaultEula;
    }
}
