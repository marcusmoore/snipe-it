@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('general.changepassword') }}
@stop

{{-- Account page content --}}
@section('content')

    <x-container class="col-md-6 col-md-offset-3">
        <x-form route="{{ route('account.password.update') }}">
            <x-box>

                <x-form.row
                    :label="trans('general.current_password')"
                    name="current_password"
                    input_div_class="col-md-5"
                >
                    <x-slot:input>
                        <input
                            class="form-control"
                            type="password"
                            id="current_password"
                            name="current_password"
                            aria-label="current_password"
                            required
                            @disabled(config('app.lock_passwords'))
                        />
                        @if (config('app.lock_passwords') === true)
                            <p class="text-warning">
                                <x-icon type="locked" /> {{ trans('general.feature_disabled') }}
                            </p>
                        @endif
                    </x-slot:input>
                </x-form.row>

                <x-form.row
                    :label="trans('general.new_password')"
                    name="password"
                    input_div_class="col-md-5"
                >
                    <x-slot:input>
                        <input
                            class="form-control"
                            type="password"
                            id="password"
                            name="password"
                            aria-label="password"
                            required
                            @disabled(config('app.lock_passwords'))
                        />
                        @if (config('app.lock_passwords') === true)
                            <p class="text-warning">
                                <x-icon type="locked" /> {{ trans('general.feature_disabled') }}
                            </p>
                        @endif
                    </x-slot:input>
                </x-form.row>

                <x-form.row
                    :label="trans('general.new_password')"
                    name="password_confirmation"
                    input_div_class="col-md-5"
                >
                    <x-slot:input>
                        <input
                            class="form-control"
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            aria-label="password_confirmation"
                            @disabled(config('app.lock_passwords'))
                        />
                        @if (config('app.lock_passwords') === true)
                            <p class="text-warning">
                                <x-icon type="locked" /> {{ trans('general.feature_disabled') }}
                            </p>
                        @endif
                    </x-slot:input>
                </x-form.row>

            </x-box>
        </x-form>
    </x-container>

@stop
