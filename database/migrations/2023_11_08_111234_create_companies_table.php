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
            $table->string('name');
            $table->string('country');
            $table->string('logo');
            $table->string('email');
            $table->string('phone');
            $table->date('creation_date');
            $table->string('status')->default('active');
            $table->string('description',5000);
            $table->integer('min_cin');
            $table->integer('max_cin');
            $table->integer('min_passport');
            $table->integer('max_passport');
            $table->string('nationality');
            $table->string('regime_social')->nullable();
            $table->string('type');
            $table->string('first_color');
            $table->string('second_color');
            $table->integer('max_telework');     //telework times
            $table->string('type_telework');
            $table->string('start_time');
            $table->string('end_time');
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
