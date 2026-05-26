<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartNtdUrl extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_ntd_url', function($table)
        {
            $table->string('url', 512)->change();
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_ntd_url', function($table)
        {
            $table->string('url', 255)->change();
        });
    }
}
