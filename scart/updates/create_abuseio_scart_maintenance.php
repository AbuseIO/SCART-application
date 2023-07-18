<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateAbuseioScartMaintenance extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_maintenance', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('module', 40);
            $table->dateTime('start');
            $table->dateTime('end');
            $table->text('note');

            $table->index(['module','start','end'],'module_start_end');

        });

    }

    public function down()
    {
        Schema::dropIfExists('abuseio_scart_maintenance');
    }
}
