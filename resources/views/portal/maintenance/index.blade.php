@extends('portal.layout')

@php
    $badge = fn ($status) => match ($status) {
        \App\Enums\MaintenanceStatus::Open => 'bg-amber-100 text-amber-700',
        \App\Enums\MaintenanceStatus::InProgress => 'bg-blue-100 text-blue-700',
        \App\Enums\MaintenanceStatus::Resolved => 'bg-green-100 text-green-700',
        \App\Enums\MaintenanceStatus::Closed => 'bg-slate-100 text-slate-600',
        \App\Enums\MaintenanceStatus::Cancelled => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

@section('content')
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-slate-900">{{ __('Maintenance') }}</h1>
        <a href="{{ route('portal.maintenance.create') }}" class="rounded bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            {{ __('New request') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700 shadow">{{ session('status') }}</div>
    @endif

    @forelse ($requests as $request)
        <a href="{{ route('portal.maintenance.show', $request) }}"
           class="mt-3 block rounded-xl bg-white p-4 shadow transition hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900">{{ $request->title }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Room') }} {{ $request->unit?->room_number }}
                        @if ($request->property)<span class="text-slate-300">·</span> {{ $request->property->name }}@endif
                    </p>
                    <p class="mt-1 text-xs text-slate-400">{{ $request->created_at?->format('d M Y H:i') }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge($request->status) }}">
                        {{ $request->status->getLabel() }}
                    </span>
                    <p class="mt-2 text-xs text-slate-500">{{ $request->priority->getLabel() }}</p>
                </div>
            </div>
        </a>
    @empty
        <div class="mt-3 rounded-xl bg-white p-6 text-center text-slate-500 shadow">
            {{ __('No maintenance requests yet.') }}
        </div>
    @endforelse
@endsection
