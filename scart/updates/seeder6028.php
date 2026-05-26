<?php namespace abuseio\scart\updates;

use abuseio\scart\models\Input_source;
use Seeder;
use Db;

class Seeder6028 extends Seeder
{
    public function run()
    {

        $codes = Db::table('abuseio_scart_input')->select('source_code')->distinct()->get();
        foreach ($codes as $code) {
            if (Db::table('abuseio_scart_input_source')->where('code','LIKE',$code->source_code)->doesntExist()) {
                Input_source::checkInsertCode($code->source_code);
            }
        }

    }

}
