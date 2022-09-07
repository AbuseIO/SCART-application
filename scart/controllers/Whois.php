<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\base\scartController;
use BackendMenu;
use Config;
use Session;
use Flash;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Systemconfig;

class Whois extends scartController {

    public $implement = [    ];

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'whois');
    }

     private $whoisfields = [
         'host_lookup',
         'host_owner',                     // host owner
         'host_country',                   // host country
         'host_abusecontact',              // host abuse contact
         'registrar_lookup',
         'registrar_owner',                // registrar owner name
         'registrar_country',              // registrar country
         'registrar_abusecontact',         // registrar abuse contact
    ];

    /**
     * Own index
     */
    public function index() {

        $this->pageTitle = 'WhoIs';

        $this->bodyClass = 'compact-container ';

        $default = Systemconfig::get('abuseio.scart::whois.provider', '');
        $provider = Session::get('whois_provider',$default);

        $providers = [
            'phpWhois' => [
                'selected' => (($provider=='phpWhois') ? 'selected="selected"' : ''),
                'value' => 'phpWhois',
                'option' => 'Open Source (free) phpWhois library [SCART current]'
                ],
            'Hexilion' => [
                'selected' => (($provider=='Hexilion') ? 'selected="selected"' : ''),
                'value' => 'Hexilion',
                'option' => 'Centralops (pay) Hexilion WhoIs provider',

            ]
        ];

        $this->vars['providers'] = $providers;

    }

    public function onWhois() {

        $provider = input('provider', '');
        if ($provider) {
            scartWhois::setProvider($provider);
            Session::put('whois_provider', $provider);
        }
        $domain = input('domain', '');
        $show = '';

        if ($domain) {

            $whois = scartWhois::getHostingInfo($domain);
            //trace_log($whois);

            if ($whois['status_success']) {

                $fields = [];
                $flds = $this->whoisfields;
                foreach ($flds AS $fld) {
                    $fields[$fld] = $whois[$fld];
                }

                $rawtext = $whois[SCART_REGISTRAR.'_rawtext'] . "\n\n" . $whois[SCART_HOSTER.'_rawtext'];

                $show = $this->makePartial('show_whois', [
                    'whois' => $fields,
                    'whoisraw' => scartWhois::htmlOutputRaw($rawtext),
                ] );

            } else {
                Flash::warning($whois['status_text']);
            }

        } else {
            Flash::warning('No input domain or IP');
        }

        return ['show_result' => $show];
    }


}
