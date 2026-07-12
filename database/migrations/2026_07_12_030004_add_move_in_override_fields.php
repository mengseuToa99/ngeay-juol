<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->text('move_in_override_reason')->nullable()->after('moved_in_by_id');
            $table->timestamp('move_in_override_at')->nullable()->after('move_in_override_reason');
            $table->foreignId('move_in_override_by_id')->nullable()->after('move_in_override_at')->constrained('users')->nullOnDelete();
            $table->date('move_in_promised_payment_date')->nullable()->after('move_in_override_by_id');
        });
    }
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('move_in_override_by_id');
            $table->dropColumn(['move_in_override_reason', 'move_in_override_at', 'move_in_promised_payment_date']);
        });
    }
};
