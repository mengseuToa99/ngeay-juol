<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_settings', function (Blueprint $table) {
            $table->decimal('usd_khr_exchange_rate', 12, 4)->nullable()->after('currency');
            $table->date('exchange_rate_date')->nullable()->after('usd_khr_exchange_rate');
            $table->string('exchange_rate_source', 64)->nullable()->after('exchange_rate_date');
            $table->timestamp('exchange_rate_fetched_at')->nullable()->after('exchange_rate_source');
        });
    }

    public function down(): void
    {
        Schema::table('property_settings', function (Blueprint $table) {
            $table->dropColumn([
                'usd_khr_exchange_rate',
                'exchange_rate_date',
                'exchange_rate_source',
                'exchange_rate_fetched_at',
            ]);
        });
    }
};
