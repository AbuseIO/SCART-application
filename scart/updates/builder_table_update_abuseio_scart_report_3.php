<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartReport3 extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->boolean('sendpolice')->nullable()->default(0);
            $table->string('sent_to_email_police', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_report', function($table)
        {
            $table->dropColumn('sendpolice');
            $table->dropColumn('sent_to_email_police');
        });
    }
}
