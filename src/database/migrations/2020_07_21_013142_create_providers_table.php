<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name', 200);
            $table->string('nit', 12)->nullable();
            $table->string('email', 100);
            $table->string('address', 200);
            $table->string('location', 200)->nullable();
            $table->text('description');
            $table->string('phone', 15);
            $table->tinyInteger('start_work');
            $table->tinyInteger('end_work');
            $table->foreignId('provider_type_id')->constrained('provider_types');
            $table->engine = 'InnoDB';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('providers');
    }
}
