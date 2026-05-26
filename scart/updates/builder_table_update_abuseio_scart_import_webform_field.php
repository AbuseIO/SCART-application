<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartImportWebformField extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_import_webform_field', function($table)
        {
            $table->string('password', 255)->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_import_webform_field', function($table)
        {
            $table->dropColumn('password');
        });
    }
}
