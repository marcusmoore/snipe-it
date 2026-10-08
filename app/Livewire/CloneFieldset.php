<?php

namespace App\Livewire;

use App\Models\CustomFieldset;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CloneFieldset extends Component
{
    #[Locked]
    public int $id;

    public string $name = '';

    public function render()
    {
        return view('livewire.clone-fieldset');
    }

    public function submit()
    {
        $this->authorize('create', CustomFieldset::class);

        $this->validate((new CustomFieldset)->rules);

        $fieldset = DB::transaction(function () {
            $original = CustomFieldset::findOrFail($this->id);

            $fieldset = new CustomFieldset(['name' => $this->name]);
            $fieldset->created_by = auth()->id();
            $fieldset->save();

            $pivot = $original->fields->mapWithKeys(function ($field) {
                return [$field->id => $field->pivot->only(['order', 'required'])];
            });

            $fieldset
                ->fields()
                ->attach($pivot);

            return $fieldset;
        });

        session()->flash('success', trans('admin/custom_fields/message.fieldset.clone.success'));

        return $this->redirectRoute('fieldsets.show', [$fieldset->id]);
    }
}
