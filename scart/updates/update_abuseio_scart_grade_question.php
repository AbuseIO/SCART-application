<?php namespace abuseio\scart\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartGradeQuestion extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_grade_question', function($table)
        {
            $table->string('url_type', 20)->default('mainurl');
            $table->string('iccam_field', 80)->nullable();
        });
    }

    public function down()
    {
        Schema::table('abuseio_scart_grade_question', function($table)
        {
            $table->dropColumn('url_type');
            $table->dropColumn('iccam_field');
        });
    }
}
