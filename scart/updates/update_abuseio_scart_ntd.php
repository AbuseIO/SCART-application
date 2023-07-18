<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartNtd extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_ntd', function($table)
        {
            $table->string('abusecontact_type', 40)->nullable()->default('unknown');
            $table->string('type', 10)->nullable()->default('unknown');
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_ntd', function($table)
        {
            $table->dropColumn('abusecontact_type');
            $table->dropColumn('type');
        });
    }
}
