<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create charge_definitions table
        Schema::create('charge_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('category', 64); // utility, rent_addon, recurring_fee, parking, internet, service, other
            $table->string('billing_type', 64); // metered, flat, recurring, one_time, manual
            $table->decimal('default_amount', 12, 4)->default(0);
            $table->string('default_currency', 3)->default('USD');
            $table->string('unit_of_measure')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('property_id');
            $table->index('landlord_id');
        });

        // 2. Add charge_definition_id to property_utilities
        Schema::table('property_utilities', function (Blueprint $table) {
            $table->foreignId('charge_definition_id')->nullable()->after('landlord_id')
                ->constrained('charge_definitions')->nullOnDelete();
        });

        // 3. Create charge_rules table
        Schema::create('charge_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_definition_id')->nullable()->constrained('charge_definitions')->cascadeOnDelete();
            $table->foreignId('property_utility_id')->nullable()->constrained('property_utilities')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('scope_type', 32); // property, unit, rental
            $table->unsignedBigInteger('scope_id');
            $table->string('state', 32)->default('normal'); // normal, free, waived, not_applicable, skipped_this_cycle, custom
            $table->decimal('amount_override', 12, 4)->nullable();
            $table->string('currency_override', 3)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'scope_type', 'scope_id']);
        });

        // 4. Create billing_run_charge_decisions table
        Schema::create('billing_run_charge_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('billing_run_id')->nullable();
            $table->foreignId('rental_id')->nullable()->constrained('rentals')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->cascadeOnDelete();
            $table->foreignId('charge_definition_id')->nullable()->constrained('charge_definitions')->cascadeOnDelete();
            $table->foreignId('property_utility_id')->nullable()->constrained('property_utilities')->cascadeOnDelete();
            $table->string('resolved_state', 32);
            $table->string('source_scope_type', 32);
            $table->unsignedBigInteger('source_scope_id')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('amount', 12, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();
        });

        // 5. Add columns to invoice_lines
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('charge_state', 32)->nullable()->after('is_waived');
            $table->string('charge_state_label')->nullable()->after('charge_state');
            $table->string('charge_state_reason')->nullable()->after('charge_state_label');
            $table->foreignId('charge_definition_id')->nullable()->after('charge_state_reason')
                ->constrained('charge_definitions')->nullOnDelete();
            $table->foreignId('charge_rule_id')->nullable()->after('charge_definition_id')
                ->constrained('charge_rules')->nullOnDelete();
        });

        // 6. Backfill existing utilities and waivers
        $this->backfill();
    }

    private function backfill(): void
    {
        // Migrate Property Utilities to Charge Definitions
        $utilities = DB::table('property_utilities')->get();
        foreach ($utilities as $util) {
            $billingType = 'flat';
            if ($util->billing_type == 1) { // Metered
                $billingType = 'metered';
            }

            $defId = DB::table('charge_definitions')->insertGetId([
                'property_id' => $util->property_id,
                'landlord_id' => $util->landlord_id,
                'name' => $util->name,
                'category' => 'utility',
                'billing_type' => $billingType,
                'default_amount' => $util->rate,
                'default_currency' => $util->currency ?? 'USD',
                'unit_of_measure' => $util->unit_of_measure,
                'is_active' => $util->is_active,
                'created_at' => $util->created_at,
                'updated_at' => $util->updated_at,
            ]);

            DB::table('property_utilities')->where('id', $util->id)->update([
                'charge_definition_id' => $defId,
            ]);
        }

        // Migrate Utility Waivers to Charge Rules
        $waivers = DB::table('utility_waivers')->get();
        foreach ($waivers as $w) {
            $scopeType = 'property';
            $scopeId = $w->property_id;
            if ($w->rental_id) {
                $scopeType = 'rental';
                $scopeId = $w->rental_id;
            } elseif ($w->unit_id) {
                $scopeType = 'unit';
                $scopeId = $w->unit_id;
            }

            $defId = DB::table('property_utilities')
                ->where('id', $w->property_utility_id)
                ->value('charge_definition_id');

            DB::table('charge_rules')->insert([
                'charge_definition_id' => $defId,
                'property_utility_id' => $w->property_utility_id,
                'landlord_id' => $w->landlord_id,
                'property_id' => $w->property_id,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'state' => $w->waived ? 'waived' : 'normal',
                'created_by_id' => $w->created_by_id,
                'created_at' => $w->created_at,
                'updated_at' => $w->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_rule_id');
            $table->dropConstrainedForeignId('charge_definition_id');
            $table->dropColumn([
                'charge_state',
                'charge_state_label',
                'charge_state_reason',
            ]);
        });

        Schema::dropIfExists('billing_run_charge_decisions');
        Schema::dropIfExists('charge_rules');

        Schema::table('property_utilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_definition_id');
        });

        Schema::dropIfExists('charge_definitions');
    }
};
