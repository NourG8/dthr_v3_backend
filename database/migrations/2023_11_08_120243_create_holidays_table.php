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
        Schema::create('holidays', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->string('raison')->nullable($value = true);
            $table->longText('dates')->nullable();
            $table->string('status')->default("active");
            $table->date('date');
            $table->integer('level');
            $table->unsignedBigInteger('user_id')->index('holidays_user_id_foreign');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
