@extends('portal.layout')

@section('content')
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-slate-900">{{ $maintenanceRequest->title }}</h1>
        <a href="{{ route('portal.maintenance.index') }}" class="text-sm font-medium text-emerald-700">{{ __('Back') }}</a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700 shadow">{{ session('status') }}</div>
    @endif

    <section class="mt-4 rounded-xl bg-white p-4 shadow">
        <div class="flex flex-wrap gap-2">
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $maintenanceRequest->status->getLabel() }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $maintenanceRequest->priority->getLabel() }}</span>
        </div>
        <p class="mt-3 text-sm text-slate-500">
            {{ __('Room') }} {{ $maintenanceRequest->unit?->room_number }}
            @if ($maintenanceRequest->property)<span class="text-slate-300">·</span> {{ $maintenanceRequest->property->name }}@endif
        </p>
        <p class="mt-4 whitespace-pre-line text-slate-800">{{ $maintenanceRequest->description }}</p>

        @if ($maintenanceRequest->getMedia('photos')->isNotEmpty())
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach ($maintenanceRequest->getMedia('photos') as $photo)
                    <a href="{{ $photo->getUrl() }}" target="_blank" class="block overflow-hidden rounded-lg bg-slate-100">
                        <img src="{{ $photo->getUrl() }}" alt="{{ __('Maintenance photo') }}" class="h-36 w-full object-cover">
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Messages') }}</h2>

    <div class="mt-3 space-y-3">
        @forelse ($maintenanceRequest->messages as $message)
            <div class="rounded-xl bg-white p-4 shadow">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900">{{ $message->sender?->name ?? __('System') }}</p>
                    <p class="text-xs text-slate-400">{{ $message->created_at?->format('d M Y H:i') }}</p>
                </div>
                <p class="mt-2 whitespace-pre-line text-slate-700">{{ $message->body }}</p>
            </div>
        @empty
            <div class="rounded-xl bg-white p-4 text-center text-slate-500 shadow">{{ __('No messages yet.') }}</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('portal.maintenance.reply', $maintenanceRequest) }}" class="mt-4 space-y-3 rounded-xl bg-white p-4 shadow">
        @csrf
        <label for="body" class="block text-sm font-medium text-slate-700">{{ __('Reply') }}</label>
        <textarea id="body" name="body" rows="4" required
                  class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('body') }}</textarea>
        @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-700">
            {{ __('Post reply') }}
        </button>
    </form>
@endsection
