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
        Schema::create('scan_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->boolean('spider_enabled')->default(true);
            $table->boolean('ajax_spider_enabled')->default(false);
            $table->boolean('passive_scan_enabled')->default(true);
            $table->boolean('active_scan_enabled')->default(true);
            $table->boolean('authentication_enabled')->default(false);
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_configurations');
    }
};
