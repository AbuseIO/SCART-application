<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateAbuseioScartImportWebform extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_import_webform', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('subject', 255);
            $table->string('status_code', 80);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('abuseio_scart_import_webform');
    }
}
