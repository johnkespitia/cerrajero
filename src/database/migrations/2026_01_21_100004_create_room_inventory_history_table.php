<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomInventoryHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_inventory_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained('room_inventory_assignments')->onDelete('set null');
            $table->string('assignable_type', 125)->nullable()->comment('App\Models\Room o App\Models\CommonArea');
            $table->unsignedBigInteger('assignable_id')->nullable()->comment('ID de habitación o zona común');
            $table->foreignId('item_id')->constrained('room_inventory_items')->onDelete('cascade');
            $table->string('action', 50)->comment('assigned, moved, status_changed, checked, removed, etc.');
            $table->string('old_assignable_type', 125)->nullable();
            $table->unsignedBigInteger('old_assignable_id')->nullable();
            $table->string('new_assignable_type', 125)->nullable();
            $table->unsignedBigInteger('new_assignable_id')->nullable();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->integer('old_quantity')->nullable();
            $table->integer('new_quantity')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('assignment_id');
            $table->index(['assignable_type', 'assignable_id'], 'history_assignable_index');
            $table->index('item_id');
            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_inventory_history');
    }
}
