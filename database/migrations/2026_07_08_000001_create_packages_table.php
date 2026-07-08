<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('slug', 30)->unique();
            $table->enum('billing_period', ['trial', 'monthly', 'yearly']);
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('sort_order')->default(99);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
