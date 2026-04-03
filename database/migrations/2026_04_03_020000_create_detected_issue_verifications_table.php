<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('detected_issue_verifications')) {
            Schema::create('detected_issue_verifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('issue_id')->index();
                $table->string('property_slug')->nullable()->index();
                $table->string('domain')->nullable()->index();
                $table->string('issue_class', 100)->nullable()->index();
                $table->string('status', 60);
                $table->timestamp('hidden_until')->nullable()->index();
                $table->string('verification_source')->nullable();
                $table->json('verification_notes')->nullable();
                $table->timestamp('verified_at')->index();
                $table->timestamps();

                $table->index(['issue_id', 'verified_at'], 'detected_issue_verification_issue_verified_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_issue_verifications');
    }
};
