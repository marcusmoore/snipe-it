@props(['asset' => $asset])
<div>
    <h1 class="text-2xl m-4">View Asset {{ $asset->asset_tag }}</h1>
    <div class="ml-4 grid grid-cols-1 md:grid-cols-2 md:gap-2">
        {{--        hmm, not sure the best way to componentize this... //shrug will ask marcus--}}
        {{--        <x-tailwind.datalist>--}}
        {{--            <x-slot name="label">--}}
        {{--                Status--}}
        {{--            </x-slot>--}}
        {{--            <x-slot name="data">--}}
        {{--                @if(@$asset->assetstatus->deployable)--}}
        {{--                   <x-tailwind.check-icon-filled/>--}}
        {{--                @else--}}
        {{--                    yellow--}}
        {{--                @endif--}}
        {{--                {{ $asset->assetstatus->name }}--}}
        {{--            </x-slot>--}}
        {{--        </x-tailwind.datalist>--}}
        <dl class="grid grid-cols-2 border rounded-md">
            <dt class="font-bold bg-gray-200 pl-2">Status</dt>
            <dd class="bg-gray-200 flex items-center">
                @if($asset->assetstatus->deployable)
                    <x-tailwind.icons.check-icon-filled/>
                @else
                    yellow
                @endif
                {{ $asset->assetstatus->name }}
            </dd>
            <dt class="font-bold pl-2">Serial</dt>
            <dd class="">{{ $asset->serial }}</dd>
            <dt class="font-bold bg-gray-200 pl-2">Category</dt>
            <dd class="bg-gray-200">{{ $asset->model->category->name }}</dd>
            <dt class="font-bold pl-2">Model</dt>
            <dd class="">{{ $asset->model->name }}</dd>
            <dt class="font-bold bg-gray-200 pl-2">Model No.</dt>
            <dd class="bg-gray-200">{{ $asset->model->model_number }}</dd>
            <dt class="font-bold pl-2">BYOD</dt>
            <dd class="flex items-center">
                @if($asset->byod)
                    <x-tailwind.icons.check-icon/>
                    Yes
                @else
                    <x-tailwind.icons.x-icon/>
                    No
                @endif
            </dd>
        </dl>

        <div class="grid-cols-subgrid row-span-3 mr-4 md:justify-self-end">
            <img alt="model image" class="h-72"
                 src={{ $asset->model->getImageUrl() }} >
            @if($asset->assigned_to)
                <x-tailwind.button>Checkin Asset</x-tailwind.button>
            @else
                <x-tailwind.button>Checkout Asset</x-tailwind.button>
            @endif
            <x-tailwind.button>
                Edit Asset
            </x-tailwind.button>
            <x-tailwind.button>Clone Asset</x-tailwind.button>
            <x-tailwind.button>Audit Asset</x-tailwind.button>
            <x-tailwind.button class="mt-6 bg-deletered">Delete Asset</x-tailwind.button>
        </div>
    </div>
</div>