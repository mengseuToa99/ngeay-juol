<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Users: demographics ──────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('phone_number');
            $table->date('dob')->nullable()->after('gender');
            $table->string('nationality')->nullable()->after('dob');
        });

        // ── Tenant profiles: extra detail ────────────────────────────
        Schema::table('tenant_profiles', function (Blueprint $table) {
            $table->string('workplace')->nullable()->after('occupation');
            $table->string('guarantor_id_number')->nullable()->after('guarantor_phone');
            $table->string('guarantor_address')->nullable()->after('guarantor_id_number');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
            $table->date('move_in_date')->nullable()->after('emergency_contact_relationship');
            $table->text('notes')->nullable()->after('move_in_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dob', 'nationality']);
        });

        Schema::table('tenant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'workplace',
                'guarantor_id_number',
                'guarantor_address',
                'emergency_contact_relationship',
                'move_in_date',
                'notes',
            ]);
        });
    }
};
