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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('country')->nullable();
            $table->string('logo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('creation_date')->nullable();
            $table->string('status')->nullable();
            $table->string('description',5000)->nullable();
            $table->integer('min_cin')->nullable();
            $table->integer('max_cin')->nullable();
            $table->integer('min_passport')->nullable();
            $table->integer('max_passport')->nullable();
            $table->string('nationality')->nullable();
            $table->string('regime_social')->nullable();
            $table->string('type')->nullable();
            $table->string('first_color')->nullable();
            $table->string('second_color')->nullable();
            $table->integer('max_telework')->nullable();     //telework times
            $table->string('type_telework')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
