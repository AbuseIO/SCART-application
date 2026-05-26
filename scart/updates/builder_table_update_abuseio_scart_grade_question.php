<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateAbuseioScartGradeQuestion extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_grade_question', function($table)
        {
            $table->boolean('required')->default(1);
        });
    }
    
    public function down()
    {
        Schema::table('abuseio_scart_grade_question', function($table)
        {
            $table->dropColumn('required');
        });
    }
}
