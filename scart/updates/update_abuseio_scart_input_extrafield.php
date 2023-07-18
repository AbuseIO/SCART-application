<?php namespace abuseio\scart\Updates;

use Db;
use Schema;
use abuseio\scart\models\Input_extrafield;
use Winter\Storm\Database\Updates\Migration;

class UpdateAbuseioScartInputExtrafield extends Migration
{
    public function up()
    {
        Schema::table('abuseio_scart_input_extrafield', function($table)
        {
            $table->text('secondvalue')->nullable();
        });

        // migrate existing values

        $migratefields = Input_extrafield::where('value','LIKE','%#%')->get();
        foreach ($migratefields AS $migratefield) {
            $arr = explode('#',$migratefield->value);
            $migratefield->value = (isset($arr[0])?$arr[0]: '');
            $migratefield->secondvalue = (isset($arr[1])?$arr[1]: '');
            $migratefield->save();
        }

    }

    public function down()
    {
        Schema::table('abuseio_scart_input_extrafield', function($table)
        {
            $table->dropColumn('secondvalue');
        });
    }
}
