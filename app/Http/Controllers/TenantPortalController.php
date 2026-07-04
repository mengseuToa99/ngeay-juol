<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-facing portal. Room accounts log in here by USERNAME (not email, and not
 * the Filament admin panel which blocks the tenant role). Read-only invoice view.
 */
class TenantPortalController extends Controller
{
    public function dashboard()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        $unit = Unit::withoutGlobalScopes()
            ->with('property')
            ->where('account_user_id', $user->getKey())
            ->first();

        $invoices = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $user->getKey())
            ->orderByDesc('issue_date')
            ->get();

        return view('portal.dashboard', compact('user', 'unit', 'invoices'));
    }

    public function invoice(Invoice $invoice)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // A tenant may only view their own room's invoices.
        abort_unless((int) $invoice->tenant_id === (int) Auth::id(), 403);

        $invoice->load(['lines', 'payments.recordedBy', 'rental.unit.property']);

        return view('portal.invoice', compact('invoice'));
    }

    public function maintenanceIndex()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $requests = MaintenanceRequest::withoutGlobalScopes()
            ->with(['property', 'unit'])
            ->where('tenant_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('portal.maintenance.index', compact('requests'));
    }

    public function maintenanceCreate()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $unit = $this->tenantUnit();

        return view('portal.maintenance.create', compact('unit'));
    }

    public function maintenanceStore(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $unit = $this->tenantUnit();

        if (! $unit) {
            throw ValidationException::withMessages([
                'unit' => __('No room is assigned to this account.'),
            ]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'integer', 'in:1,2,3,4'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $unit->loadMissing('property', 'activeRental');

        $maintenanceRequest = MaintenanceRequest::withoutGlobalScopes()->create([
            'tenant_id' => Auth::id(),
            'landlord_id' => $unit->landlord_id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'rental_id' => $unit->activeRental?->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'],
        ]);

        foreach ($request->file('photos', []) as $photo) {
            $maintenanceRequest
                ->addMedia($photo)
                ->toMediaCollection('photos');
        }

        return redirect()
            ->route('portal.maintenance.show', $maintenanceRequest)
            ->with('status', __('Maintenance request submitted.'));
    }

    public function maintenanceShow(MaintenanceRequest $maintenanceRequest)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        abort_unless((int) $maintenanceRequest->tenant_id === (int) Auth::id(), 403);

        $maintenanceRequest->load(['property', 'unit', 'messages.sender', 'media']);

        return view('portal.maintenance.show', compact('maintenanceRequest'));
    }

    public function maintenanceReply(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        abort_unless((int) $maintenanceRequest->tenant_id === (int) Auth::id(), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $maintenanceRequest->messages()->create([
            'sender_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('portal.maintenance.show', $maintenanceRequest)
            ->with('status', __('Reply posted.'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function tenantUnit(): ?Unit
    {
        return Unit::withoutGlobalScopes()
            ->with(['property', 'activeRental'])
            ->where('account_user_id', Auth::id())
            ->orWhereHas('activeRental', fn ($query) => $query->where('tenant_id', Auth::id()))
            ->first();
    }
}
