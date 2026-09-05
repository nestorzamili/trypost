<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 250)->nullable();
            $table->string('status')->default('idle');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_conversations');
    }
};
