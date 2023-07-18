<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateAbuseioScartInputVerify extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_input_verify', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('workuser_id');
            $table->string('grade_code', 100)->nullable();
            $table->integer('input_id');
            $table->string('status', 100);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('abuseio_scart_input_verify');
    }
}
