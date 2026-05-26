<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartImportWebformField3 extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_import_webform_field', function($table)
        {
            $table->boolean('not_report')->nullable()->default(0);
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_import_webform_field', function($table)
        {
            $table->dropColumn('not_report');
        });
    }
}
