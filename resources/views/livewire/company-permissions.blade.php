<div>
    <div class="panel panel-default">
        <div class="panel-body">
            <select wire:model="selectedCompany">
                <option value=""></option>
                @foreach($availableCompanies as $company)
                    <option>{{ $company['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @foreach($selectedCompanies as $company)
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    {{ $company['name'] }}
                    <button
                            wire:click="removeCompany('{{ $company['id'] }}')"
                            type="button"
                            class="close"
                            aria-label="Close"
                    ><span aria-hidden="true">&times;</span></button>
                </h3>
            </div>
            <div class="panel-body">
                @foreach($groups as $group)
                    <label class="form-control">
                        {{ Form::checkbox('', '1', null, []) }}
                        {{ $group['name'] }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
