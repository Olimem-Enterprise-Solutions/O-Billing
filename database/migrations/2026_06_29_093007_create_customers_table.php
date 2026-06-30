<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete(); // suburb
            $table->string('account_number');
            $table->string('name');
            $table->enum('type', ['residential', 'business', 'government'])->default('residential');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->decimal('property_value', 15, 2)->nullable(); // for per_property_value rates
            $table->char('currency', 3)->default('ZAR');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['municipality_id', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
