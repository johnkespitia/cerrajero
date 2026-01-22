<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommonAreasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('common_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 125);
            $table->string('code', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('location', 250)->nullable()->comment('Ubicación física');
            $table->enum('area_type', ['pool', 'garden', 'terrace', 'lounge', 'restaurant', 'gym', 'spa', 'other'])->default('other');
            $table->integer('capacity')->nullable()->comment('Capacidad máxima de personas');
            $table->string('image_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('area_type');
            $table->index('active');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('common_areas');
    }
}
