<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('subscriptions', function (Blueprint $table) {
        $table->boolean('is_trial')->default(false)->after('plan_type');
        $table->timestamp('trial_expires_at')->nullable()->after('is_trial');
    });
}

public function down()
{
    Schema::table('subscriptions', function (Blueprint $table) {
        $table->dropColumn(['is_trial', 'trial_expires_at']);
    });
}
};
