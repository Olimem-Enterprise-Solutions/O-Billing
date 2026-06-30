<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();          // suburb (billing level)
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();       // service variant
            // Interpreted by the parent service type's billing_basis:
            //   flat              -> rate = monthly charge
            //   per_property_value-> rate = charge per 1 currency unit of property value, per month
            //   per_unit          -> rate = price per unit
            $table->decimal('rate', 15, 6);
            $table->char('currency', 3)->default('ZAR');
            // Sage transaction (revenue) code this charge posts to in the GL.
            $table->string('tr_code')->nullable();
            $table->date('effective_from')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['area_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
