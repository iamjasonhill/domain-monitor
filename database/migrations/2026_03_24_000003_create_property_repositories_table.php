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
        if (! Schema::hasTable('property_repositories')) {
            Schema::create('property_repositories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('web_property_id');
                $table->string('repo_name');
                $table->string('repo_provider', 30)->default('local_only');
                $table->string('repo_url')->nullable();
                $table->string('local_path')->nullable();
                $table->string('default_branch')->nullable();
                $table->string('deployment_branch')->nullable();
                $table->string('framework')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
                $table->index(['web_property_id', 'is_primary']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_repositories');
    }
};
