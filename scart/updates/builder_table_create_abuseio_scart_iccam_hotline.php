<?php namespace abuseio\scart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateAbuseioScartIccamHotline extends Migration
{
    public function up()
    {
        Schema::create('abuseio_scart_iccam_hotline', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('hotlineid')->unsigned();
            $table->string('country', 255);
            $table->string('country_code', 4);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('abuseio_scart_iccam_hotline');
    }
}