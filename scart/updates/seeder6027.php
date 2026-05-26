<?php namespace abuseio\scart\updates;

use Seeder;
use Db;

class Seeder6027 extends Seeder
{
    public function run()
    {
        if (Db::table('abuseio_scart_input_status')->where('code',SCART_STATUS_FIRST_POLICE_CHECKONLINE_MANUAL)->doesntExist()) {
            $ins = Db::table('abuseio_scart_input_status')->insert([
                'sortnr' => 6,
                'code' => SCART_STATUS_FIRST_POLICE_CHECKONLINE_MANUAL,
                'lang' => 'en',
                'title' => 'First police, checkonline (manual)',
                'description' => 'First police and then checkonline (manual)',
            ]);
        }
    }

}
