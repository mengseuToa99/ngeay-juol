<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\RentalStatus;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantInvoiceViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_tenant_can_view_their_own_invoice_with_correct_layout(): void
    {
        [$tenant, $landlord, $property, $unit] = $this->tenantRoom('101', 'main');

        $invoice = Invoice::create([
            'rental_id' => $tenant->rentalsAsTenant()->first()->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-9999',
            'amount_due' => 500.0,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-15',
            'due_date' => '2026-07-31',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        $this->actingAs($tenant)
            ->get(route('portal.invoice', $invoice))
            ->assertStatus(200)
            ->assertSee($invoice->invoice_number)
            ->assertSee($property->name)
            ->assertSee(__('Room') . ' 101')
            ->assertSee(__('Bill to'))
            ->assertSee($tenant->name);
    }

    public function test_tenant_cannot_view_another_tenants_invoice(): void
    {
        [$tenant] = $this->tenantRoom('101', 'main');
        [$otherTenant, $otherLandlord, $otherProperty] = $this->tenantRoom('202', 'other');

        $otherInvoice = Invoice::create([
            'rental_id' => $otherTenant->rentalsAsTenant()->first()->id,
            'property_id' => $otherProperty->id,
            'landlord_id' => $otherLandlord->id,
            'tenant_id' => $otherTenant->id,
            'invoice_number' => 'INV-8888',
            'amount_due' => 600.0,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-15',
            'due_date' => '2026-07-31',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        $this->actingAs($tenant)
            ->get(route('portal.invoice', $otherInvoice))
            ->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$tenant, $landlord, $property] = $this->tenantRoom('101', 'main');

        $invoice = Invoice::create([
            'rental_id' => $tenant->rentalsAsTenant()->first()->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-9999',
            'amount_due' => 500.0,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-15',
            'due_date' => '2026-07-31',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        $this->get(route('portal.invoice', $invoice))
            ->assertRedirect(route('login'));
    }

    /** @return array{0: User, 1: User, 2: Property, 3: Unit} */
    protected function tenantRoom(string $room, string $suffix): array
    {
        $landlord = User::create([
            'name' => "Landlord {$suffix}",
            'email' => "landlord-{$suffix}@example.com",
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $tenant = User::create([
            'name' => "Tenant {$suffix}",
            'email' => "tenant-{$suffix}@example.com",
            'password' => bcrypt('password'),
        ]);
        $tenant->assignRole('tenant');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => "Property {$suffix}",
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'account_user_id' => $tenant->id,
            'room_number' => $room,
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'monthly_rent' => 500,
            'security_deposit' => 500,
            'status' => RentalStatus::Active,
            'start_date' => now()->toDateString(),
        ]);

        return [$tenant, $landlord, $property, $unit];
    }

    public function test_shared_room_account_cannot_view_old_rental_invoices(): void
    {
        $landlord = User::create([
            'name' => 'Landlord Main',
            'email' => 'landlord-main@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $tenant = User::create([
            'name' => 'Tenant Main',
            'email' => 'tenant-main@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant->assignRole('tenant');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Main',
        ]);

        // 1. Create one unit with one shared room account user.
        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'account_user_id' => $tenant->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        // 2. Create an old ended rental for that unit using the shared account as tenant_id.
        $oldRental = Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'monthly_rent' => 500,
            'security_deposit' => 500,
            'status' => RentalStatus::Expired,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        // 3. Create an old invoice for that old rental.
        $oldInvoice = Invoice::create([
            'rental_id' => $oldRental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-OLD',
            'amount_due' => 500.0,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-01-31',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        // 4. Create a new active rental for the same unit using the same shared account as tenant_id.
        $newRental = Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'monthly_rent' => 500,
            'security_deposit' => 500,
            'status' => RentalStatus::Active,
            'start_date' => '2026-07-01',
        ]);

        // 5. Create a new invoice for the new active rental.
        $newInvoice = Invoice::create([
            'rental_id' => $newRental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-NEW',
            'amount_due' => 500.0,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-15',
            'due_date' => '2026-07-31',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        // 6. Acting as the shared room account
        $this->actingAs($tenant);

        // dashboard sees the new invoice
        $this->get(route('portal.dashboard'))
            ->assertStatus(200)
            ->assertSee($newInvoice->invoice_number)
            // dashboard does not see the old invoice
            ->assertDontSee($oldInvoice->invoice_number);

        // opening the new invoice returns 200
        $this->get(route('portal.invoice', $newInvoice))
            ->assertStatus(200);

        // opening the old invoice returns 403
        $this->get(route('portal.invoice', $oldInvoice))
            ->assertStatus(403);
    }

    public function test_dedicated_tenant_account_can_view_its_own_rental_invoices(): void
    {
        $landlord = User::create([
            'name' => 'Landlord Main',
            'email' => 'landlord-main@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $tenant = User::create([
            'name' => 'Tenant Main',
            'email' => 'tenant-main@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant->assignRole('tenant');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Main',
        ]);

        // Dedicated tenant account unit has NO unit where account_user_id = $tenant->id
        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'account_user_id' => null, // no shared room account
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $rental = Rental::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'monthly_rent' => 500,
            'security_deposit' => 500,
            'status' => RentalStatus::Active,
            'start_date' => '2026-07-01',
        ]);

        $invoice = Invoice::create([
            'rental_id' => $rental->id,
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'invoice_number' => 'INV-DEDICATED',
            'amount_due' => 500.0,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-07-15',
            'due_date' => '2026-07-31',
            'payment_status' => InvoiceStatus::Pending,
        ]);

        $this->actingAs($tenant);

        $this->get(route('portal.dashboard'))
            ->assertStatus(200)
            ->assertSee($invoice->invoice_number);

        $this->get(route('portal.invoice', $invoice))
            ->assertStatus(200);
    }
}
