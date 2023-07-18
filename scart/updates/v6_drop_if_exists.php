<?php namespace abuseio\scart\Updates;

use Db;
use Log;
use Schema;
use Winter\Storm\Database\Updates\Migration;

class V6DropIfExists extends Migration
{
    public function up()
    {

        // take down all tables (..)

        $databasename = env('DB_DATABASE', '');
        if ($databasename) {
            $tables = Db::select("show tables like 'abuseio_scart_%' ");
            foreach ($tables AS $table) {
                foreach ($table AS $fld => $val) {
                    Log::debug("D-Drop $fld=$val");
                    Schema::dropIfExists($val);
                }
            }
        }


    }

    public function down() {

    }

}

