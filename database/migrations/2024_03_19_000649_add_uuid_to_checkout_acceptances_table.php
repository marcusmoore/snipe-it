<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUuidToCheckoutAcceptancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checkout_acceptances', function (Blueprint $table) {
            $table->uuid('uuid')->after('declined_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('checkout_acceptances', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}
