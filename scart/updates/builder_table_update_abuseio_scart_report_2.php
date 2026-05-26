<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartReport2 extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->boolean('archivedatabase')->default(0);
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->dropColumn('archivedatabase');
        });
    }
}
