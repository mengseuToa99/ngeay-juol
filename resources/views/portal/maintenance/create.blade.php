@extends('portal.layout')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-slate-900">{{ __('New maintenance request') }}</h1>
        <a href="{{ route('portal.maintenance.index') }}" class="text-sm font-medium text-emerald-700">{{ __('Back') }}</a>
    </div>

    @if (! $unit)
        <div class="mt-4 rounded-xl bg-white p-6 text-slate-600 shadow">
            {{ __('No room is assigned to this account.') }}
        </div>
    @else
        <div class="mt-3 rounded-xl bg-white p-4 shadow">
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Your room') }}</p>
            <p class="mt-1 font-semibold text-slate-900">
                {{ __('Room') }} {{ $unit->room_number }}
                @if ($unit->property)<span class="text-slate-400">·</span> <span class="text-slate-600">{{ $unit->property->name }}</span>@endif
            </p>
        </div>

        <form method="POST" action="{{ route('portal.maintenance.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4 rounded-xl bg-white p-4 shadow">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium text-slate-700">{{ __('Title') }}</label>
                <input id="title" name="title" value="{{ old('title') }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="priority" class="block text-sm font-medium text-slate-700">{{ __('Priority') }}</label>
                <select id="priority" name="priority"
                        class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @foreach (\App\Enums\MaintenancePriority::cases() as $priority)
                        <option value="{{ $priority->value }}" @selected((int) old('priority', \App\Enums\MaintenancePriority::Medium->value) === $priority->value)>
                            {{ $priority->getLabel() }}
                        </option>
                    @endforeach
                </select>
                @error('priority')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="6" required
                          class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="photos" class="block text-sm font-medium text-slate-700">{{ __('Photos') }}</label>
                <input id="photos" name="photos[]" type="file" accept="image/*" multiple
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
                @error('photos')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('photos.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-700">
                {{ __('Submit request') }}
            </button>
        </form>
    @endif
@endsection
