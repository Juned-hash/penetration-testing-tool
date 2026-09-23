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
        Schema::create('authentication_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('none'); // none, form, browser, token
            $table->string('login_url')->nullable();
            $table->string('username_field')->nullable();
            $table->string('password_field')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted at model layer
            $table->string('token_name')->nullable();
            $table->text('token_value')->nullable(); // encrypted at model layer
            $table->string('login_button_selector')->nullable();
            $table->string('logged_in_indicator')->nullable();
            $table->string('logged_out_indicator')->nullable();
            $table->string('authenticated_url')->nullable();
            $table->json('additional_configuration')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authentication_configurations');
    }
};
