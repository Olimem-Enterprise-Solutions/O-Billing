<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->date('period_month');          // first day of the billed month
            $table->string('description')->nullable();
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->unsignedInteger('invoice_count')->default(0);
            // Totals per currency (multi-currency runs): {"ZAR": 12345.67, "USD": 890.00}
            $table->json('currency_totals')->nullable();
            $table->timestamp('run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_runs');
    }
};
