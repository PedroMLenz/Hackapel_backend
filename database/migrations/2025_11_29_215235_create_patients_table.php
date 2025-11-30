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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('state')->nullable();
            $table->string('city');
            $table->string('neighborhood');
            $table->string('street');
            $table->string('number');
            $table->string('complement')->nullable();
            $table->date('date_of_birth');
            $table->integer('age')->nullable();
            $table->json('diseases')->nullable();
            $table->string('telegram_chat_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
