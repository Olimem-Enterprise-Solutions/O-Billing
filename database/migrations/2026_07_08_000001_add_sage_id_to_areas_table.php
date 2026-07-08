<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A stable reference to the source Sage node (e.g. "area:12", "region:1"),
        // so the `sage:import` command can be re-run to refresh without creating
        // duplicate areas — Sage area names and codes are not unique on their own.
        Schema::table('areas', function (Blueprint $table): void {
            $table->string('sage_id', 64)->nullable()->after('code');
            $table->index(['municipality_id', 'sage_id']);
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table): void {
            $table->dropIndex(['municipality_id', 'sage_id']);
            $table->dropColumn('sage_id');
        });
    }
};
