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
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('club_id')->unique();
            $table->string('name');
            $table->string('championship');
            $table->date('founding_date');
            $table->string('city');
            $table->string('state');
            $table->string('country');
            $table->string('stadium');
            $table->string('president');
            $table->string('nickname')->nullable();
            $table->json('colors');
            $table->integer('titles');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
