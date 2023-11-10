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
        Schema::create('holiday_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_responsible');
            $table->string('status')->default("active");
            $table->integer('is_rejected_prov');        // (0 cas accepté)  et (1 cas rejeté)
            $table->integer('is_archive');
            $table->integer('level');
            $table->longText('raison_reject')->nullable($value = true);
            $table->unsignedBigInteger('holiday_id')->index('holiday_histories_holiday_id_foreign');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_histories');
    }
};
