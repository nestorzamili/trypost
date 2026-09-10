<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 10);
            $table->string('label', 100);
            $table->json('colors')->nullable();
            $table->string('brand_color', 9)->nullable();
            $table->string('background_color', 9)->nullable();
            $table->string('text_color', 9)->nullable();
            $table->string('headline_font')->nullable();
            $table->string('body_font')->nullable();
            $table->string('label_font')->nullable();
            $table->string('accent_font')->nullable();
            $table->text('visual_notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'language_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_variants');
    }
};
