<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('creation_id')->unique();
            $table->string('status')->default('pending_text');
            $table->string('format');
            $table->string('template')->default('image_card');
            $table->foreignUuid('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('image_expected')->default(0);
            $table->unsignedSmallInteger('image_done')->default(0);
            $table->foreignUuid('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->json('structured')->nullable();
            $table->string('error_phase')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'creation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
