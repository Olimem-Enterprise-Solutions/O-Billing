<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks each Sage bridge operation (import, preview, post, push) as it moves
 * through the queue. The cloud UI dispatches a job, then reads this row to show
 * the user progress/results, since the work runs asynchronously on the on-site
 * worker rather than in the web request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sage_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who triggered it

            $table->string('type');                                   // e.g. import_ledger, post_run, push_property
            $table->enum('status', ['queued', 'running', 'done', 'failed'])->default('queued');

            $table->nullableMorphs('subject');                        // related BillingRun / Invoice / Customer, if any
            $table->json('params')->nullable();                       // job input
            $table->json('result')->nullable();                       // structured result (counts, preview summary)
            $table->text('message')->nullable();                      // human-readable summary or error

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sage_operations');
    }
};
