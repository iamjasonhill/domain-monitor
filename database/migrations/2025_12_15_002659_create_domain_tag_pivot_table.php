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
        Schema::create('domain_tag', function (Blueprint $table) {
            $table->uuid('domain_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->primary(['domain_id', 'tag_id']);
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('domain_tags')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_tag');
    }
};
