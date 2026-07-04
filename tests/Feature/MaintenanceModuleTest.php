<?php

namespace Tests\Feature;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Filament\Resources\MaintenanceRequestResource;
use App\Filament\Resources\MaintenanceRequestResource\Pages\ListMaintenanceRequests;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_tenant_can_create_maintenance_request_for_their_room(): void
    {
        [$tenant, $landlord, $property, $unit] = $this->tenantRoom();

        $this->actingAs($tenant)
            ->post(route('portal.maintenance.store'), [
                'title' => 'Leaking sink',
                'description' => 'Water is leaking under the sink.',
                'priority' => MaintenancePriority::High->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenance_requests', [
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'title' => 'Leaking sink',
            'priority' => MaintenancePriority::High->value,
            'status' => MaintenanceStatus::Open->value,
        ]);
    }

    public function test_tenant_cannot_view_or_reply_to_another_tenants_request(): void
    {
        [$tenant] = $this->tenantRoom('101');
        [$otherTenant, $otherLandlord, $otherProperty, $otherUnit] = $this->tenantRoom('202', 'other');

        $request = MaintenanceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'landlord_id' => $otherLandlord->id,
            'property_id' => $otherProperty->id,
            'unit_id' => $otherUnit->id,
            'title' => 'Broken light',
            'description' => 'The bathroom light is out.',
            'priority' => MaintenancePriority::Medium,
            'status' => MaintenanceStatus::Open,
        ]);

        $this->actingAs($tenant)
            ->get(route('portal.maintenance.show', $request))
            ->assertForbidden();

        $this->actingAs($tenant)
            ->post(route('portal.maintenance.reply', $request), [
                'body' => 'I should not be able to reply.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('maintenance_messages', [
            'request_id' => $request->id,
            'sender_id' => $tenant->id,
        ]);
    }

    public function test_tenant_can_reply_to_their_own_request(): void
    {
        [$tenant, $landlord, $property, $unit] = $this->tenantRoom();

        $request = MaintenanceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'title' => 'AC issue',
            'description' => 'The AC stopped cooling.',
            'priority' => MaintenancePriority::Medium,
            'status' => MaintenanceStatus::Open,
        ]);

        $this->actingAs($tenant)
            ->post(route('portal.maintenance.reply', $request), [
                'body' => 'It is getting worse.',
            ])
            ->assertRedirect(route('portal.maintenance.show', $request));

        $this->assertDatabaseHas('maintenance_messages', [
            'request_id' => $request->id,
            'sender_id' => $tenant->id,
            'body' => 'It is getting worse.',
        ]);
    }

    public function test_landlord_resource_query_is_scoped_to_their_requests(): void
    {
        [$tenant, $landlord, $property, $unit] = $this->tenantRoom();
        [$otherTenant, $otherLandlord, $otherProperty, $otherUnit] = $this->tenantRoom('202', 'other');

        $owned = MaintenanceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'title' => 'Owned request',
            'description' => 'Visible',
            'priority' => MaintenancePriority::Medium,
            'status' => MaintenanceStatus::Open,
        ]);

        $other = MaintenanceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'landlord_id' => $otherLandlord->id,
            'property_id' => $otherProperty->id,
            'unit_id' => $otherUnit->id,
            'title' => 'Other request',
            'description' => 'Hidden',
            'priority' => MaintenancePriority::Medium,
            'status' => MaintenanceStatus::Open,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('landlord'));
        $this->actingAs($landlord);

        $ids = MaintenanceRequestResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($owned->id, $ids);
        $this->assertNotContains($other->id, $ids);

        Livewire::test(ListMaintenanceRequests::class)
            ->assertCanSeeTableRecords([$owned])
            ->assertCanNotSeeTableRecords([$other]);
    }

    /** @return array{0: User, 1: User, 2: Property, 3: Unit} */
    protected function tenantRoom(string $room = '101', string $suffix = 'main'): array
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
}
