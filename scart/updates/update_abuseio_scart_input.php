<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartInput extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_input', function($table)
        {
            $table->timestamp('checkonline_lock')->nullable();
            $table->index('checkonline_lock');

        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_input', function($table)
        {
            $table->dropColumn('checkonline_lock');
        });
    }
}
