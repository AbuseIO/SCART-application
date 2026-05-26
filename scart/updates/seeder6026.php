<?php namespace abuseio\scart\Updates;

use Seeder;
use Db;

class Seeder6026 extends Seeder
{
    public function run()
    {
        if (Db::table('abuseio_scart_input_status')->where('code',SCART_STATUS_DIRECT_POLICE)->doesntExist()) {
            $ins = Db::table('abuseio_scart_input_status')->insert([
                'sortnr' => 6,
                'code' => SCART_STATUS_DIRECT_POLICE,
                'lang' => 'en',
                'title' => 'Direct police',
                'description' => 'Direct to police',
            ]);
        }
    }

}
