<div>
    <!-- EULA text -->
    <div class="form-group {{ $errors->has('eula_text') ? 'error' : '' }}">
        <label for="eula_text" class="col-md-3 control-label">{{ trans('admin/categories/general.eula_text') }}</label>
        <div class="col-md-7">
            {{ Form::textarea('eula_text', null, ['wire:model.live.debounce' => 'eulaText', 'class' => 'form-control', 'aria-label'=>'eula_text', 'disabled' => $this->eulaTextDisabled]) }}
            <p class="help-block">{!! trans('admin/categories/general.eula_text_help') !!} </p>
            <p class="help-block">{!! trans('admin/settings/general.eula_markdown') !!} </p>
            {!! $errors->first('eula_text', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
        </div>
        @if ($this->eulaTextDisabled)
            <input type="hidden" name="eula_text" wire:model.live="eulaText" />
        @endif
    </div>

    <!-- Use default checkbox -->
    <div class="form-group">
        <div class="col-md-9 col-md-offset-3">
            <label
                @class([
                    'form-control',
                    'form-control--disabled' => !$defaultEulaText,
                ])
            >
                <input
                    type="checkbox"
                    name="use_default_eula"
                    wire:model.boolean.live="useDefaultEula"
                    aria-label="use_default_eula"
                    @disabled(!$defaultEulaText)
                    @class(['disabled' => !$defaultEulaText])
                >
                @if ($defaultEulaText)
                    <span>{!! trans('admin/categories/general.use_default_eula') !!}</span>
                @else
                    <span>{!! trans('admin/categories/general.use_default_eula_disabled') !!}</span>
                @endif
            </label>
        </div>
    </div>

    <!-- Require Acceptance -->
    <div class="form-group">
        <div class="col-md-9 col-md-offset-3">
            <label class="form-control">
                {{ Form::checkbox('require_acceptance', '1', null, ['wire:model.live' => 'requireAcceptance', 'aria-label'=>'require_acceptance']) }}
                {{ trans('admin/categories/general.require_acceptance') }}
            </label>
        </div>
    </div>

    <!-- Email on Checkin -->
    <div class="form-group">
        <div class="col-md-9 col-md-offset-3">
            <label class="form-control">
                <input
                    type="checkbox"
                    name="checkin_email"
                    wire:model.live="sendCheckInEmail"
                    @disabled($this->sendCheckInEmailDisabled)
                    aria-label="checkin_email"
                >
                {{ trans('admin/categories/general.checkin_email') }}
            </label>
            @if ($this->shouldDisplayEmailMessage)
                <div class="callout callout-info">
                    <i class="far fa-envelope"></i>
                    <span>{{ $this->emailMessage }}</span>
                </div>
            @endif
            @if ($this->sendCheckInEmailDisabled)
                <input type="hidden" name="checkin_email" wire:model.live="sendCheckInEmail" />
            @endif
        </div>
    </div>
</div>
