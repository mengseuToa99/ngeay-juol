<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('occupant_gender')->nullable()->after('occupant_address');
            $table->date('occupant_dob')->nullable()->after('occupant_gender');
            $table->string('occupant_nationality')->nullable()->after('occupant_dob');
            $table->string('occupant_workplace')->nullable()->after('occupant_nationality');
            
            $table->string('emergency_contact_name')->nullable()->after('occupant_workplace');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
            
            $table->string('guarantor_name')->nullable()->after('emergency_contact_relationship');
            $table->string('guarantor_phone')->nullable()->after('guarantor_name');
            $table->string('guarantor_id_number')->nullable()->after('guarantor_phone');
            $table->string('guarantor_address')->nullable()->after('guarantor_id_number');
            
            $table->text('notes')->nullable()->after('terms_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'occupant_gender',
                'occupant_dob',
                'occupant_nationality',
                'occupant_workplace',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'guarantor_name',
                'guarantor_phone',
                'guarantor_id_number',
                'guarantor_address',
                'notes',
            ]);
        });
    }
};
