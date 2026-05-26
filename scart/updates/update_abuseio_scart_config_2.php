<?php namespace abuseio\scart\updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartConfig2 extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_config', function($table)
        {
            if (Schema::hasColumn('abuseio_scart_config','scheduler-archive-only_delete')) {
                $table->dropColumn('scheduler-archive-only_delete');
                $table->dropColumn('scheduler-archive-archive_time');
            }
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_config', function($table)
        {
            $table->boolean('scheduler-archive-only_delete')->default(true)->nullable();
            $table->smallinteger('scheduler-archive-archive_time')->default(7)->nullable();
        });
    }
}
