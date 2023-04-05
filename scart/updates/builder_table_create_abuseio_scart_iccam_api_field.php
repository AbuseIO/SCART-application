<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateAbuseioScartIccamApiField extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_iccam_api_field', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('iccam_field', 255);
            $table->string('iccam_id', 255);
            $table->string('iccam_name', 255);
            $table->string('scart_field', 255)->nullable();
            $table->string('scart_code', 255)->nullable();

            $table->index(['scart_field','scart_code'],'scart_field_code');

        });
    }

    public function down()
    {
        Schema::dropIfExists('abuseio_scart_iccam_api_field');
    }
}
