<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartImportexportJob extends Migration
{
    public function up()
    {

        if (!Schema::hasColumn('abuseio_scart_importexport_job', 'postdata')) {
            Schema::table('abuseio_scart_importexport_job', function($table)
            {
                $table->text('postdata')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::table('abuseio_scart_importexport_job', function($table)
        {
            $table->dropColumn('postdata');
        });
    }
}
