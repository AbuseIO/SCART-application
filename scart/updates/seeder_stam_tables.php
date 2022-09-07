<?php namespace abuseio\scart\Updates;

use Seeder;
use Db;

class SeederStamTables extends Seeder
{
    public function run() {

        $status_code = [
            ['sortnr' => 1,'code' => 'open', 'lang' => 'en', 'title' => 'open', 'description' => 'open'],
            ['sortnr' => 2,'code' => 'scheduler_scrape', 'lang' => 'en', 'title' => 'scrape (scheduler)', 'description' => 'scrape & analyze whois'],
            ['sortnr' => 3,'code' => 'work', 'lang' => 'en', 'title' => 'working', 'description' => 'working'],
            ['sortnr' => 4,'code' => 'cannot_scrape', 'lang' => 'en', 'title' => 'cannot scrape (scheduler)', 'description' => 'cannot scrape and/or get whois info'],
            ['sortnr' => 5,'code' => 'grade', 'lang' => 'en', 'title' => 'classify', 'description' => 'classify'],
            ['sortnr' => 6,'code' => 'first_police', 'lang' => 'en', 'title' => 'first police', 'description' => 'first inform police'],
            ['sortnr' => 7,'code' => 'abusecontact_changed', 'lang' => 'en', 'title' => 'abusecontact is changed', 'description' => 'abusecontact is changed - wait for approval'],
            ['sortnr' => 8,'code' => 'scheduler_checkonline', 'lang' => 'en', 'title' => 'checkonline (scheduler)', 'description' => 'check if still online'],
            ['sortnr' => 9,'code' => 'scheduler_checkonline_manual', 'lang' => 'en', 'title' => 'checkonline (manual)', 'description' => 'manual check online'],
            ['sortnr' => 10,'code' => 'close_offline', 'lang' => 'en', 'title' => 'offline (handled)', 'description' => 'image offline'],
            ['sortnr' => 11,'code' => 'close', 'lang' => 'en', 'title' => 'close', 'description' => 'close (done)'],
            ['sortnr' => 12,'code' => 'close_double', 'lang' => 'en', 'title' => 'close double', 'description' => 'Closed because double url'],
            ['sortnr' => 13,'code' => 'scheduler_ai_analyze', 'lang' => 'en', 'title' => 'AI analyze (scheduler)', 'description' => 'Analyze by the AI module'],
        ];
        Db::table('abuseio_scart_input_status')->truncate();
        Db::table('abuseio_scart_input_status')->insert($status_code);

        Db::table('abuseio_scart_input_type')->truncate();
        Db::table('abuseio_scart_input_type')->insert([
            ['sortnr' => 0,'code' => 'notdetermined', 'lang' => 'en', 'title' => 'Not determined', 'description' => 'Not determined'],
            ['sortnr' => 1,'code' => 'website', 'lang' => 'en', 'title' => 'website', 'description' => 'website'],
            ['sortnr' => 2,'code' => 'filehost', 'lang' => 'en', 'title' => 'filehost', 'description' => 'filehost'],
            ['sortnr' => 3,'code' => 'imagehost', 'lang' => 'en', 'title' => 'imagehost', 'description' => 'imagehost'],
            ['sortnr' => 4,'code' => 'imagestore', 'lang' => 'en', 'title' => 'imagestore', 'description' => 'imagestore'],
            ['sortnr' => 5,'code' => 'imageboard', 'lang' => 'en', 'title' => 'imageboard', 'description' => 'imageboard'],
            ['sortnr' => 6,'code' => 'bannersite', 'lang' => 'en', 'title' => 'bannersite', 'description' => 'bannersite'],
            ['sortnr' => 7,'code' => 'linksite', 'lang' => 'en', 'title' => 'linksite', 'description' => 'linksite'],
            ['sortnr' => 8,'code' => 'socialsite', 'lang' => 'en', 'title' => 'socialsite', 'description' => 'socialsite'],
            ['sortnr' => 9,'code' => 'redirector', 'lang' => 'en', 'title' => 'redirector', 'description' => 'redirector'],
            ['sortnr' => 10,'code' => 'webarchived', 'lang' => 'en', 'title' => 'webarchived', 'description' => 'webarchived'],
            ['sortnr' => 11,'code' => 'searchprovider', 'lang' => 'en', 'title' => 'searchprovider', 'description' => 'searchprovider'],
            ['sortnr' => 12,'code' => 'blog', 'lang' => 'en', 'title' => 'blog', 'description' => 'blog'],
            ['sortnr' => 13,'code' => 'forum', 'lang' => 'en', 'title' => 'forum', 'description' => 'forum'],
        ]);

        Db::table('abuseio_scart_input_source')->truncate();
        Db::table('abuseio_scart_input_source')->insert([
            ['sortnr' => 1,'code' => 'police', 'lang' => 'en', 'title' => 'police', 'description' => 'by police'],
            ['sortnr' => 2,'code' => 'helpwanted', 'lang' => 'en', 'title' => 'helpwanted', 'description' => 'Help wanted'],
            ['sortnr' => 3,'code' => 'stopitnow', 'lang' => 'en', 'title' => 'stop it now', 'description' => 'stop it now'],
            ['sortnr' => 4,'code' => 'analyst', 'lang' => 'en', 'title' => 'analyst', 'description' => 'by analyst'],
            ['sortnr' => 5,'code' => 'iccam', 'lang' => 'en', 'title' => 'ICCAM', 'description' => 'ICCAM report'],
            ['sortnr' => 6,'code' => 'webform', 'lang' => 'en', 'title' => 'web form', 'description' => 'website form'],

        ]);

        Db::table('abuseio_scart_grade_status')->truncate();
        Db::table('abuseio_scart_grade_status')->insert([
            ['sortnr' => 1,'code' => 'unset', 'lang' => 'en', 'title' => 'unset', 'description' => 'not classified'],
            ['sortnr' => 2,'code' => 'illegal', 'lang' => 'en', 'title' => 'illegal', 'description' => 'Illegal'],
            ['sortnr' => 3,'code' => 'ignore', 'lang' => 'en', 'title' => 'ignore', 'description' => 'Ignore (skip)'],
            ['sortnr' => 4,'code' => 'not_illegal', 'lang' => 'en', 'title' => 'not illegal', 'description' => 'Not illegal'],
        ]);

        Db::table('abuseio_scart_ntd_status')->truncate();
        Db::table('abuseio_scart_ntd_status')->insert([
            ['sortnr' => 1,'code' => 'init', 'lang' => 'en', 'title' => 'init', 'description' => 'init (created)'],
            ['sortnr' => 2,'code' => 'grouping', 'lang' => 'en', 'title' => 'grouping', 'description' => 'check online (group) for 24 hours'],
            ['sortnr' => 3,'code' => 'queue_directly', 'lang' => 'en', 'title' => 'queue directly', 'description' => 'queue directly'],
            ['sortnr' => 4,'code' => 'queue_directly_police', 'lang' => 'en', 'title' => 'queue directly for police', 'description' => 'queue directly for police'],
            ['sortnr' => 5,'code' => 'queued', 'lang' => 'en', 'title' => 'queued', 'description' => 'queued to mailserver'],
            ['sortnr' => 6,'code' => 'sent_failed', 'lang' => 'en', 'title' => 'sent failed', 'description' => 'sent message failed'],
            ['sortnr' => 7,'code' => 'sent_succes', 'lang' => 'en', 'title' => 'sent succes', 'description' => 'sent message success'],
            ['sortnr' => 8,'code' => 'close', 'lang' => 'en', 'title' => 'close', 'description' => 'close (offline)'],
            ['sortnr' => 9,'code' => 'sent_api_failed', 'lang' => 'en', 'title' => 'sent API failed', 'description' => 'sent API failed'],
            ['sortnr' => 10,'code' => 'sent_api_succes', 'lang' => 'en', 'title' => 'sent API success', 'description' => 'sent API success'],
        ]);

        Db::table('abuseio_scart_rule_type')->truncate();
        Db::table('abuseio_scart_rule_type')->insert([
            ['sortnr' => 1,'code' => 'do_not_scrape', 'lang' => 'en', 'title' => 'Do not scrape',
                'description' => 'Ignore/skip domain'],
            ['sortnr' => 2,'code' => 'host_whois', 'lang' => 'en', 'title' => 'Custom hoster whois',
                'description' => 'Set (overrule) hoster'],
            ['sortnr' => 3,'code' => 'registrar_whois', 'lang' => 'en', 'title' => 'Custom registrar whois',
                'description' => 'Set (overrule) registrar'],
            ['sortnr' => 4,'code' => 'site_owner', 'lang' => 'en', 'title' => 'Site owner',
                'description' => 'Send hoster NTD also to site owner'],
            ['sortnr' => 5,'code' => 'proxy_service', 'lang' => 'en', 'title' => 'Proxy service owner',
                'description' => 'Proxy service based on IP'],
            ['sortnr' => 6,'code' => 'direct_classify_illegal', 'lang' => 'en', 'title' => 'Direct classify ILLEGAL',
                'description' => 'Directly classify ILLEGAL without human verify'],
            ['sortnr' => 7,'code' => 'direct_classify_not_illegal', 'lang' => 'en', 'title' => 'Direct classify NOT ILLEGAL',
                'description' => 'Directly classify NOT ILLEGAL without human verify'],
            ['sortnr' => 8,'code' => 'link_checker', 'lang' => 'en', 'title' => 'Link checker',
                'description' => 'Link checker based on addon (webform interface)'],
            ['sortnr' => 9,'code' => 'proxy_service_api', 'lang' => 'en', 'title' => 'Proxy service API',
                'description' => 'Proxy service based on addon (API interface)'],
        ]);

        $status_code = [
            ['sortnr' => 1,'code' => 'init', 'lang' => 'en', 'title' => 'init', 'description' => 'init'],
            ['sortnr' => 2,'code' => 'created', 'lang' => 'en', 'title' => 'created', 'description' => 'created'],
            ['sortnr' => 3,'code' => 'sent', 'lang' => 'en', 'title' => 'sent', 'description' => 'sent'],
            ['sortnr' => 4,'code' => 'skipped', 'lang' => 'en', 'title' => 'skipped', 'description' => 'skipped'],
            ['sortnr' => 5,'code' => 'close', 'lang' => 'en', 'title' => 'close', 'description' => 'close'],
        ];
        Db::table('abuseio_scart_alert_status')->truncate();
        Db::table('abuseio_scart_alert_status')->insert($status_code);

        $addon_types = [
            ['sortnr' => 1,'code' => 'ai_image_analyzer', 'lang' => 'en', 'title' => 'AI image analyzer ', 'description' => 'AI image analyzer'],
            ['sortnr' => 2,'code' => 'link_checker', 'lang' => 'en', 'title' => 'Link checker', 'description' => 'url link checker'],
            ['sortnr' => 3,'code' => 'proxy_service_api', 'lang' => 'en', 'title' => 'Proxy service api', 'description' => 'Proxy service API'],
            ['sortnr' => 4,'code' => 'ntd_api', 'lang' => 'en', 'title' => 'NTD API', 'description' => 'NTD API'],
        ];
        Db::table('abuseio_scart_addon_type')->truncate();
        Db::table('abuseio_scart_addon_type')->insert($addon_types);

    }
}
