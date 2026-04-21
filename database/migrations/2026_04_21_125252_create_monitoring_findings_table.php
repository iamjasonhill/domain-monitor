<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('issue_id')->unique();
            $table->string('lane', 80);
            $table->string('finding_type', 120);
            $table->string('issue_type', 40);
            $table->string('scope_type', 40)->default('web_property');
            $table->uuid('domain_id')->nullable();
            $table->uuid('web_property_id')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamp('first_detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->foreign('domain_id')->references('id')->on('domains')->nullOnDelete();
            $table->foreign('web_property_id')->references('id')->on('web_properties')->nullOnDelete();
            $table->index(['lane', 'status']);
            $table->index(['finding_type', 'status']);
            $table->index(['web_property_id', 'finding_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_findings');
    }
};
