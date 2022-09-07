<?php namespace abuseio\scart\Updates;

use Seeder;
use Db;

class Seeder604 extends Seeder
{
    public function run()
    {
        
        Db::table('abuseio_scart_iccam_hotline')->truncate();
        Db::table('abuseio_scart_iccam_hotline')->insert([
            ['hotlineid' => 3,'country' => 'Brasil', 'country_code' => 'br'],
            ['hotlineid' => 4,'country' => 'Colombia', 'country_code' => ''],
            ['hotlineid' => 6,'country' => 'Hungary', 'country_code' => 'hu'],
            ['hotlineid' => 12,'country' => 'Thailand', 'country_code' => 'th'],
            ['hotlineid' => 14,'country' => 'Australia', 'country_code' => 'au'],
            ['hotlineid' => 15,'country' => 'Canada', 'country_code' => 'ca'],
            ['hotlineid' => 16,'country' => 'Taiwan', 'country_code' => 'tw'],
            ['hotlineid' => 18, 'country' => 'South Korea','country_code' => 'kr'],
            ['hotlineid' => 22, 'country' => 'United States','country_code' => 'us'],
            ['hotlineid' => 23, 'country' => 'Austria','country_code' => 'at'],
            ['hotlineid' => 24, 'country' => 'Belgim','country_code' => 'be'],
            ['hotlineid' => 28, 'country' => 'Denmark','country_code' => 'dk'],
            ['hotlineid' => 29, 'country' => 'Estonia','country_code' => 'ee'],
            ['hotlineid' => 30, 'country' => 'Finland','country_code' => 'fi'],
            ['hotlineid' => 31, 'country' => 'France','country_code' => 'fr'],
            ['hotlineid' => 32, 'country' => 'Germany','country_code' => 'de'],
            ['hotlineid' => 35, 'country' => 'Greece','country_code' => 'gr'],
            ['hotlineid' => 36, 'country' => 'Ireland','country_code' => 'ie'],
            ['hotlineid' => 39, 'country' => 'Latvia','country_code' => 'lv'],
            ['hotlineid' => 40, 'country' => 'Lithuania','country_code' => 'lt'],
            ['hotlineid' => 41, 'country' => 'Luxembourg','country_code' => 'lu'],
            ['hotlineid' => 43, 'country' => 'Netherlands','country_code' => 'nl'],
            ['hotlineid' => 44, 'country' => 'Poland','country_code' => 'pl'],
            ['hotlineid' => 48, 'country' => 'Slovenia','country_code' => 'si'],
            ['hotlineid' => 51, 'country' => 'United Kingdom','country_code' => 'uk'],
            ['hotlineid' => 52, 'country' => 'Sweden','country_code' => 'se'],
            ['hotlineid' => 55, 'country' => 'Romania','country_code' => 'ro'],
            ['hotlineid' => 57, 'country' => 'Camboda','country_code' => ''],
            ['hotlineid' => 58, 'country' => 'Japan','country_code' => 'jp'],
            ['hotlineid' => 59, 'country' => 'Czech Republic','country_code' => 'cz'],
            ['hotlineid' => 60, 'country' => 'Portugal','country_code' => 'pt'],
            ['hotlineid' => 61, 'country' => 'Cyprus','country_code' => 'cy'],
        ]);


    }
}