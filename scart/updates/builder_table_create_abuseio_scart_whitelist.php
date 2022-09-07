<?php namespace abuseio\scart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateAbuseioScartWhitelist extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_whitelist', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->text('email');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('abuseio_scart_whitelist');
    }
}
