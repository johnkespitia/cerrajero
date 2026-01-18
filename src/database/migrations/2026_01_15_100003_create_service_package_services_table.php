<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicePackageServicesTable extends Migration
{
    public function up()
    {
        Schema::create('service_package_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained()->onDelete('cascade');
            $table->foreignId('additional_service_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['service_package_id', 'additional_service_id'], 'svc_pkg_svc_pkg_svc_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_package_services');
    }
}
