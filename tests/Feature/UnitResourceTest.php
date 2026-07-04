<?php

namespace Tests\Feature;

use App\Enums\BillingType;
use App\Enums\ReadingType;
use App\Filament\Resources\UnitResource\Pages\EditUnit;
use App\Models\Property;
use App\Models\PropertyUtility;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityUsage;
use App\Support\ActiveProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Database\Seeders\RolesAndPermissionsSeeder;

class UnitResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('landlord'));
    }

    protected function tearDown(): void
    {
        ActiveProperty::clear();
        parent::tearDown();
    }

    public function test_landlord_can_mount_and_execute_meter_readings_on_edit_unit_page(): void
    {
        $landlord = User::create([
            'name' => 'Landlord User',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('landlord');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Property Alpha',
        ]);
        ActiveProperty::set($property->id);

        $utility = PropertyUtility::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Electricity',
            'billing_type' => BillingType::Metered,
            'rate' => 0.15,
            'unit_of_measure' => 'kWh',
        ]);

        $unit = Unit::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'room_number' => '101',
            'room_type' => 'Standard',
            'rent_amount' => 500,
        ]);

        $this->actingAs($landlord);

        Livewire::test(EditUnit::class, [
            'record' => $unit->getKey(),
        ])
            ->assertActionExists('meterReadings')
            ->callAction('meterReadings', data: [
                'reading_date' => now()->toDateString(),
                'reading_type' => ReadingType::Actual->value,
                'meters' => [
                    $utility->id => '120.5',
                ],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('utility_usages', [
            'unit_id' => $unit->id,
            'property_utility_id' => $utility->id,
            'new_reading' => 120.5,
            'recorded_by_id' => $landlord->id,
        ]);
    }
}
