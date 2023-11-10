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
        Schema::create('teleworks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('raison');
            $table->longText('date');
            $table->string('status')->default("active");
            $table->integer('level');
            $table->unsignedBigInteger('user_id')->index('teleworks_user_id_foreign');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teleworks');
    }
};
