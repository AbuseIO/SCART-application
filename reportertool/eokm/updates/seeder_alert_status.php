<?php namespace ReporterTool\EOKM\Updates;

use Seeder;
use Db;

class SeederAlertStatus extends Seeder
{
    public function run() {

        $status_code = [
            ['sortnr' => 1,'code' => 'init', 'lang' => 'en', 'title' => 'init', 'description' => 'init'],
            ['sortnr' => 2,'code' => 'created', 'lang' => 'en', 'title' => 'created', 'description' => 'created'],
            ['sortnr' => 3,'code' => 'sent', 'lang' => 'en', 'title' => 'sent', 'description' => 'sent'],
            ['sortnr' => 4,'code' => 'skipped', 'lang' => 'en', 'title' => 'skipped', 'description' => 'skipped'],
            ['sortnr' => 5,'code' => 'close', 'lang' => 'en', 'title' => 'close', 'description' => 'close'],
        ];

        Db::table('reportertool_eokm_alert_status')->truncate();
        Db::table('reportertool_eokm_alert_status')->insert($status_code);

    }


}