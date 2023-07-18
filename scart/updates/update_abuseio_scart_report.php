<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartReport extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->text('export_columns')->nullable();
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->dropColumn('export_columns');
        });
    }
}
