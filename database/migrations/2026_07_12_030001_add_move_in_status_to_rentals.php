<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('move_in_status')->default('draft')->after('status');
            $table->timestamp('moved_in_at')->nullable()->after('move_in_status');
            $table->foreignId('moved_in_by_id')->nullable()->after('moved_in_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moved_in_by_id');
            $table->dropColumn(['move_in_status', 'moved_in_at']);
        });
    }
};
