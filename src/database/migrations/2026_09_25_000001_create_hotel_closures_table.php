<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHotelClosuresTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hotel_closures')) {
            return;
        }

        Schema::create('hotel_closures', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('closure_type')->default('total');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('start_date');
            $table->index('end_date');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_closures');
    }
}
