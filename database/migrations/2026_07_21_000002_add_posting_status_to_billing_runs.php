<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a completed billing run has been posted to Sage. Because
 * posting now runs asynchronously on the on-site worker, the UI reads these to
 * show the run's Sage state without re-querying Sage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_runs', function (Blueprint $table): void {
            // null = not posted; 'posting' = queued/running; 'posted' = done; 'failed'
            $table->string('posting_status')->nullable()->after('run_at');
            $table->timestamp('posted_at')->nullable()->after('posting_status');
        });
    }

    public function down(): void
    {
        Schema::table('billing_runs', function (Blueprint $table): void {
            $table->dropColumn(['posting_status', 'posted_at']);
        });
    }
};
