<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomInventoryAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_inventory_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignable_type', 125)->comment('App\Models\Room o App\Models\CommonArea');
            $table->unsignedBigInteger('assignable_id')->comment('ID de la habitación o zona común');
            $table->foreignId('item_id')->constrained('room_inventory_items')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->enum('status', ['available', 'in_use', 'damaged', 'maintenance', 'missing', 'replaced'])->default('available');
            $table->text('condition_notes')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('last_checked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['assignable_type', 'assignable_id'], 'assignable_index');
            $table->index('item_id');
            $table->index('status');
            $table->index('active');
            $table->index('assigned_by');
            $table->index('last_checked_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_inventory_assignments');
    }
}
