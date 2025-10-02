<x-input.select
    {{ $attributes }}
    :options="[
        'firstname.lastname' => trans('admin/settings/general.username_formats.firstname_lastname_format'),
        'firstname' => trans('admin/settings/general.username_formats.first_name_format'),
        'lastname' => trans('admin/settings/general.username_formats.last_name_format'),
        'filastname' => trans('admin/settings/general.username_formats.filastname_format'),
        'lastnamefirstinitial' => trans('admin/settings/general.username_formats.lastnamefirstinitial_format'),
        'firstname_lastname' => trans('admin/settings/general.username_formats.firstname_lastname_underscore_format'),
        'firstinitial.lastname' => trans('admin/settings/general.username_formats.firstinitial_lastname'),
        'lastname_firstinitial' => trans('admin/settings/general.username_formats.lastname_firstinitial'),
        'lastname.firstinitial' => trans('admin/settings/general.username_formats.lastname_dot_firstinitial_format'),
        'firstnamelastname' => trans('admin/settings/general.username_formats.firstnamelastname'),
        'firstnamelastinitial' => trans('admin/settings/general.username_formats.firstnamelastinitial'),
        'lastname.firstname' => trans('admin/settings/general.username_formats.lastnamefirstname'),
    ]"
/>
