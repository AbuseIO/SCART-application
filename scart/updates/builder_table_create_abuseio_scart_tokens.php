<?php namespace abuseio\scart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateAbuseioScartTokens extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_tokens', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('name', 300);
            $table->text('bearertoken');
            $table->string('refreshtoken', 100);
            $table->dateTime('expires_in');
            $table->integer('expires_in_number');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('abuseio_scart_tokens');
    }
}
