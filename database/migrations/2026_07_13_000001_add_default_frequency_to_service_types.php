<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table): void {
            // The cadence this service is normally billed at. Nullable = ad-hoc /
            // no fixed cadence. A billing run bills the services whose frequency it
            // targets (e.g. a monthly run bills the monthly services).
            $table->string('default_frequency')->nullable()->after('billing_basis');
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table): void {
            $table->dropColumn('default_frequency');
        });
    }
};
