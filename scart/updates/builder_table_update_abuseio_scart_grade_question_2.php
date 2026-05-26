<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartGradeQuestion2 extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_grade_question', function($table)
        {
            $table->string('default', 255)->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_grade_question', function($table)
        {
            $table->dropColumn('default');
        });
    }
}
