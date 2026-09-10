<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->boolean('apply_brand_visuals')->default(true)->after('template');
            $table->json('reference_media_ids')->nullable()->after('apply_brand_visuals');
            $table->boolean('use_brand_references')->default(true)->after('reference_media_ids');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->dropColumn(['apply_brand_visuals', 'reference_media_ids', 'use_brand_references']);
        });
    }
};
