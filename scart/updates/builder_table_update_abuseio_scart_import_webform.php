<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartImportWebform extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_import_webform', function($table)
        {
            $table->boolean('ntd_use_standard_fields')->nullable()->default(false);
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_import_webform', function($table)
        {
            $table->dropColumn('ntd_use_standard_fields');
        });
    }
}
