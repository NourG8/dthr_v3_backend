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
        Schema::create('faq_departments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('is_deleted')->default(0);
            $table->unsignedBigInteger('faq_id')->index('faq_departments_faq_id_foreign');
            $table->unsignedBigInteger('department_id')->index('faq_departments_department_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_departments');
    }
};
