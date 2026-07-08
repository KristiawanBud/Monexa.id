<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->enum('discount_type', ['percent', 'fixed'])->nullable()->after('price');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->string('discount_label', 60)->nullable()->after('discount_value');
            $table->timestamp('discount_starts_at')->nullable()->after('discount_label');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_label', 'discount_starts_at', 'discount_ends_at']);
        });
    }
};
