<?php namespace abuseio\scart\updates;

use Seeder;
use Db;

class SeederAIattributes extends Seeder
{
    public function run() {


        Db::table('abuseio_scart_input_extrafield')->where('type','PWCAI')
            ->where('label','expliciete_content_cat')
            ->whereIn('value',['Expliciet'])
            ->update([
            'value' => '1',
        ]);

        Db::table('abuseio_scart_input_extrafield')->where('type','PWCAI')
            ->where('label','expliciete_content_cat')
            ->whereIn('value',['Niet Expliciet','Kans op expliciete content'])
            ->update([
                'value' => '0',
            ]);

    }
}
