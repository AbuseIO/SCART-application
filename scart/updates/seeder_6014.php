<?php namespace abuseio\scart\Updates;

use Seeder;
use Db;

class Seeder6014 extends Seeder
{
    public function run()
    {

        if (Db::table('abuseio_scart_input_status')->where('code','close_offline_manual')->doesntExist()) {
            $ins = Db::table('abuseio_scart_input_status')->insert([
                'sortnr' => 10,
                'code' => 'close_offline_manual',
                'lang' => 'en',
                'title' => 'offline (manual)',
                'description' => 'url offline',
            ]);
        }

        $upd = Db::table('abuseio_scart_input_status')->where('code','close_offline')->update([
            'title' => 'offline (scheduler)',
            'description' => 'url offline',
        ]);


    }
}