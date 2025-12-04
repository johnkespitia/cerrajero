<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriceFieldsToReservationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('calculated_price', 10, 2)->nullable()->after('total_price');
            $table->boolean('manual_price_override')->default(false)->after('calculated_price');
            $table->json('price_breakdown')->nullable()->after('manual_price_override');
            $table->string('promotion_code')->nullable()->after('price_breakdown');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promotion_code');
            $table->decimal('final_price', 10, 2)->nullable()->after('discount_amount');
            $table->integer('extra_beds')->default(0)->after('infants');
            $table->boolean('early_check_in')->default(false)->after('check_out_time');
            $table->boolean('late_check_out')->default(false)->after('early_check_in');
            $table->decimal('early_check_in_fee', 10, 2)->default(0)->after('late_check_out');
            $table->decimal('late_check_out_fee', 10, 2)->default(0)->after('early_check_in_fee');
            $table->time('scheduled_check_in_time')->nullable()->after('late_check_out_fee');
            $table->time('scheduled_check_out_time')->nullable()->after('scheduled_check_in_time');
            $table->boolean('reminder_sent')->default(false)->after('email_sent_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            $table->boolean('check_in_reminder_sent')->default(false)->after('reminder_sent_at');
            $table->timestamp('check_in_reminder_sent_at')->nullable()->after('check_in_reminder_sent');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'calculated_price',
                'manual_price_override',
                'price_breakdown',
                'promotion_code',
                'discount_amount',
                'final_price',
                'extra_beds',
                'early_check_in',
                'late_check_out',
                'early_check_in_fee',
                'late_check_out_fee',
                'scheduled_check_in_time',
                'scheduled_check_out_time',
                'reminder_sent',
                'reminder_sent_at',
                'check_in_reminder_sent',
                'check_in_reminder_sent_at',
            ]);
        });
    }
}

