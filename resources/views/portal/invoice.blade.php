@extends('portal.layout')

@section('content')
    <div class="mb-4">
        <a href="{{ route('portal.dashboard') }}" class="text-sm text-emerald-700 hover:underline">&larr; {{ __('Back to invoices') }}</a>
    </div>

    @include('components.invoice-slip-modal', ['invoice' => $invoice])
@endsection

