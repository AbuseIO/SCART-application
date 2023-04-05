<?php namespace abuseio\scart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartInput extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_input', function($table)
        {
            $table->timestamp('checkonline_lock')->nullable();
            $table->index('checkonline_lock');

        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_input', function($table)
        {
            $table->dropColumn('checkonline_lock');
        });
    }
}