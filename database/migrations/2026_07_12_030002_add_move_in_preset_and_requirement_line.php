<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_settings', fn (Blueprint $table) => $table->string('move_in_preset')->default('flexible')->after('upfront_deposit_months'));
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('rental_move_in_requirement_id')->nullable()->after('invoice_id')->constrained('rental_move_in_requirements')->nullOnDelete();
            $table->string('billing_classification')->nullable()->after('line_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) { $table->dropConstrainedForeignId('rental_move_in_requirement_id'); $table->dropColumn('billing_classification'); });
        Schema::table('property_settings', fn (Blueprint $table) => $table->dropColumn('move_in_preset'));
    }
};
