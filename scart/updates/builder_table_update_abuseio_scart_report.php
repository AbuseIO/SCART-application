<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartReport extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->boolean('anonymous')->nullable()->default(false);
            $table->string('sent_to_email', 255)->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->dropColumn('anonymous');
            $table->dropColumn('sent_to_email');
        });
    }
}
