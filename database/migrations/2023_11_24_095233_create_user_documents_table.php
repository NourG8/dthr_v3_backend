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
        Schema::create('user_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->date('start_date')->format('d/m/Y')->nullable();
            $table->date('end_date')->format('d/m/Y')->nullable();
            $table->double('salary')->nullable();
            $table->string('place_of_work')->nullable();
            $table->string('start_time_work')->nullable();
            $table->string('end_time_work')->nullable();
            $table->string('trial_period')->nullable();  //periode d'essai
            $table->string('file')->nullable();  // le contrat final        
            $table->enum('status', ['Draft', 'Edited', 'Delivered','Signed','Canceled','Ended'])->default("Draft")->nullable();
            $table->date('date_status')->nullable($value = true);
            $table->string('raison')->nullable($value = true);
            $table->integer('only_physical')->nullable();
            $table->unsignedBigInteger('document_id')->index('user_documents_document_id_foreign');
            $table->unsignedBigInteger('user_id')->index('user_documents_user_id_foreign');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
