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
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('ZAP');
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('risk')->nullable();
            $table->string('confidence')->default('Medium'); // High, Medium, Low, False Positive
            $table->string('severity')->default('Medium'); // Critical, High, Medium, Low, Informational
            $table->text('url')->nullable();
            $table->string('method')->nullable();
            $table->string('parameter')->nullable();
            $table->text('attack')->nullable();
            $table->text('evidence')->nullable();
            $table->text('description')->nullable();
            $table->text('impact')->nullable();
            $table->text('solution')->nullable();
            $table->text('reference')->nullable();
            $table->string('cwe_id')->nullable();
            $table->string('wasc_id')->nullable();
            $table->string('wstg_id')->nullable();
            $table->string('status')->default('Open'); // Open, Confirmed, False Positive, Accepted Risk, Resolved, Retest Required, Closed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
