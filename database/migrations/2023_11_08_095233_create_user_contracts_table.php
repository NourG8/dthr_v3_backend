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
        Schema::create('user_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->date('start_date')->format('d/m/Y')->nullable();
            $table->date('end_date')->format('d/m/Y')->nullable();
            $table->double('salary')->nullable();
            $table->string('place_of_work')->nullable();
            $table->string('start_time_work')->nullable();
            $table->string('end_time_work')->nullable();
            $table->string('trial_period')->nullable();  //periode d'essai
            $table->string('file_contract')->nullable();  // le contrat final
            $table->enum('status', ['Draft', 'Edited', 'Delivered','Signed','Canceled','Ended'])->default("Draft");
            $table->date('date_status')->nullable($value = true);
            $table->string('raison')->nullable($value = true);
            $table->integer('only_physical');
            $table->unsignedBigInteger('contract_id')->index('user_contracts_contract_id_foreign');
            $table->unsignedBigInteger('user_id')->index('user_contracts_user_id_foreign');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_contracts');
    }
};
