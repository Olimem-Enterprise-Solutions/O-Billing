<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // Province, District, City/Town, Suburb
            $table->unsignedSmallInteger('level');  // 1 = top (Province) ... n = bottom
            $table->boolean('is_billing_level')->default(false); // suburb level: tariffs & customers attach here
            $table->timestamps();
            $table->unique(['municipality_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_types');
    }
};
