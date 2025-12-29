<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('git_commit', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('deployed_at');
            $table->timestamps();

            $table->index(['domain_id', 'deployed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
