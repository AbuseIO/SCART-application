<?php namespace ReporterTool\EOKM\Controllers;

use reportertool\eokm\classes\ertAnalyzeInput;
use reportertool\eokm\classes\ertController;
use BackendMenu;
use Config;
use Session;
use Flash;
use reportertool\eokm\classes\ertWhois;

class Whois extends ertController {

    public $implement = [    ];
    
    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'utility', 'Whois');
    }

    /**
     * Own index
     */
    public function index() {

        $this->pageTitle = 'WhoIs';

        $this->bodyClass = 'compact-container ';

        $default = Config::get('reportertool.eokm::whois.provider', '');
        $provider = Session::get('whois_provider',$default);

        $providers = [
            'phpWhois' => [
                'selected' => (($provider=='phpWhois') ? 'selected="selected"' : ''),
                'value' => 'phpWhois',
                'option' => 'Open Source (free) phpWhois library [ERT current]'
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
            ertWhois::setProvider($provider);
            Session::put('whois_provider', $provider);
        }
        $domain = input('domain', '');
        $show = '';

        if ($domain) {

            $whois = ertWhois::getHostingInfo($domain);
            //trace_log($whois);

            if ($whois['status_success']) {

                $fields = [];
                $flds = ertAnalyzeInput::$_whoisfields;
                foreach ($flds AS $fld) {
                    $fields[$fld] = $whois[$fld];
                }

                $rawtext = $whois[ERT_REGISTRAR.'_rawtext'] . "\n\n" . $whois[ERT_HOSTER.'_rawtext'];

                $show = $this->makePartial('show_whois', [
                    'whois' => $fields,
                    'whoisraw' => ertWhois::htmlOutputRaw($rawtext),
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
