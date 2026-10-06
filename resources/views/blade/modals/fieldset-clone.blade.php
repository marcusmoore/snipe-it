@props(['id'])

{{-- See snipeit_modals.js for what powers this --}}
<x-modals
    id="cloneFieldsetModal"
    stacked
    :title="trans('admin/custom_fields/general.clone_fieldset')"
    :action="route('fieldsets.clone', $id)"
    :submit_label="trans('general.clone')"
    form_attrs='accept-charset="UTF-8"'
>
    <input type="hidden" name="id" value="{{ $id }}">

    <x-form.row
        :label="trans('general.name')"
        name="name"
    />
</x-modals>
