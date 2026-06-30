<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which service variants each customer is billed for. A vacant stand may
        // carry property rates but not refuse, so subscriptions are explicit, and
        // the chosen variant (e.g. High vs Low Density) is set per customer here.
        Schema::create('customer_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_service');
    }
};
