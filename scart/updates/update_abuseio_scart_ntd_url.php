<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartNtdUrl extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_ntd_url', function($table)
        {
            $table->string('ip', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_ntd_url', function($table)
        {
            $table->dropColumn('ip');
        });
    }
}
