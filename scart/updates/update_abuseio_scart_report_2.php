<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartReport2 extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->string('filter_type', 255)->default('exporturl')->nullable();
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->dropColumn('filter_type');
        });
    }
}

