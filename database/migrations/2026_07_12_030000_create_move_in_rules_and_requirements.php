<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('move_in_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('charge_type');
            $table->string('calculation_type');
            $table->decimal('calculation_value', 12, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('due_timing')->default('before_move_in');
            $table->boolean('blocks_move_in')->default(true);
            $table->decimal('minimum_required', 12, 2)->nullable();
            $table->boolean('refundable')->default(false);
            $table->string('application_policy')->nullable();
            $table->boolean('allow_rental_override')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['property_id', 'is_active']);
        });

        Schema::create('rental_move_in_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('move_in_rule_id')->nullable()->constrained('move_in_rules')->nullOnDelete();
            $table->string('name');
            $table->string('charge_type');
            $table->string('calculation_type');
            $table->decimal('calculation_value', 12, 4)->nullable();
            $table->json('calculation_inputs')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('minimum_required', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status')->default('outstanding');
            $table->string('due_timing')->default('before_move_in');
            $table->boolean('blocks_move_in')->default(true);
            $table->boolean('refundable')->default(false);
            $table->string('application_policy')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('override_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();
            $table->index(['rental_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_move_in_requirements');
        Schema::dropIfExists('move_in_rules');
    }
};
