<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-customer charge override, in the customer's currency, for one
        // billing period at the service's own frequency. When set it takes
        // precedence over the suburb tariff (Gokwe South prices licences, lease
        // rents and levies per individual account, not per ward).
        Schema::table('customer_service', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_service', function (Blueprint $table): void {
            $table->dropColumn('amount');
        });
    }
};
