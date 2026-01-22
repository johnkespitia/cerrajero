<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomInventoryCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 125);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable()->comment('Icono para UI');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_inventory_categories');
    }
}
