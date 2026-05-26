<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateAbuseioScartImportWebformField extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_import_webform_field', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('import_webform_id')->unsigned();
            $table->string('name', 255);
            $table->string('type', 80);
            $table->boolean('password_protected')->default(false);
            $table->string('import_fieldname', 255);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('abuseio_scart_import_webform_field');
    }
}
