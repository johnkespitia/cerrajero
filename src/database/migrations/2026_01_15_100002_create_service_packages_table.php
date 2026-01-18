<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicePackagesTable extends Migration
{
    public function up()
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('room_type_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index('status');
            $table->index('room_type_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_packages');
    }
}
