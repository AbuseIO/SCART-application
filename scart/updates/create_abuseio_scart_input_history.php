<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateAbuseioScartInputHistory extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_input_history', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('input_id')->unsigned();
            $table->string('tag', 40);
            $table->text('old')->default('')->nullable();
            $table->text('new')->default('')->nullable();
            $table->string('comment')->default('')->nullable();
            $table->integer('workuser_id')->unsigned()->default(0)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('abuseio_scart_input_history');
    }
}
