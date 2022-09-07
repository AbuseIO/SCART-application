<?php namespace abuseio\scart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartConfig extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_config', function($table)
        {
            $table->string('scheduler-checkntd-mode', 20)->default('CRON');
            $table->smallInteger('scheduler-checkntd-realtime_inputs_max')->default(10);
            $table->smallInteger('scheduler-checkntd-realtime_min_diff_spindown')->default(15);
            $table->smallInteger('scheduler-checkntd-realtime_look_again')->default(120);
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_config', function($table)
        {
            $table->dropColumn('scheduler-checkntd-mode');
            $table->dropColumn('scheduler-checkntd-realtime_inputs_max');
            $table->dropColumn('scheduler-checkntd-realtime_min_diff_spindown');
            $table->dropColumn('scheduler-checkntd-realtime_look_again');
        });
    }
}