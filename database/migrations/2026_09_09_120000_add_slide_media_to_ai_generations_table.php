<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-slide rendered media for a carousel generation, keyed by slide index.
     * Lets RenderPostImages resume a partially-failed carousel without
     * re-rendering (and re-billing) the slides that already succeeded, and
     * without overwriting user edits on the draft.
     */
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->json('slide_media')->nullable()->after('structured');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table): void {
            $table->dropColumn('slide_media');
        });
    }
};
