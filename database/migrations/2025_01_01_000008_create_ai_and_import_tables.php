<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_acs_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->json('messages')->nullable();
            $table->integer('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index('user_id', 'idx_acs_user');
        });

        Schema::create('import_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('user_id', 26)->nullable(false);
            $table->foreign('user_id', 'fk_ims_user')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('file_path', 255);
            $table->enum('source_app', [
                'generic_csv', 'generic_excel',
                'bca', 'mandiri', 'bni', 'bri',
                'jenius', 'gopay', 'ovo', 'dana', 'shopeepay',
                'ai_detect',
            ])->default('ai_detect');
            $table->enum('status', ['uploaded', 'parsing', 'preview', 'importing', 'done', 'failed'])->default('uploaded');
            $table->json('preview_data')->nullable();
            $table->json('column_mapping')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('errors')->nullable();
            $table->string('ai_provider', 30)->default('gemini');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index('user_id', 'idx_ims_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
