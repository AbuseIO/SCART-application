<?php

namespace abuseio\scart\classes\base;

use abuseio\scart\models\Input;
use Backend\Classes\Controller;
use BackendMenu;
use BackendAuth;
use Lang;

class scartController extends Controller {


    public $scartFinderInputListWidget = null;
    public $scartFinderDomainruleListWidget = null;
    public $scartFinderNtdListWidget = null;
    public $finderConfig;// belangrijk


    public function __construct() {
        parent::__construct();

        // initieer finder widget. Reachable with $this->widget->finder
        (new \abuseio\scart\widgets\Finder($this))->bindToController();

        if (BackendAuth::check()) {
            $this->init();
        }
    }

    public function init() {

        $this->finderConfig = $this->makeConfig(plugins_path('abuseio/scart/controllers/finder/config/config.yaml'));

        // @TO-DO; make this "filter" var specific for FINDER function to avoid conflicts with other session vars
        $filters = current(\Session::get('filter', []));

        // init list widget
        $this->scartFinderInputListWidget = $this->widget->finder->makeList(['name' => 'input', 'alias' => 'scartFinderInputListWidget', 'filters' => $filters],'', true);
        $this->scartFinderInputListWidget->bindToController();
        $this->scartFinderNtdListWidget = $this->widget->finder->makeList(['name' => 'ntd', 'alias' => 'scartFinderNtdListWidget', 'filters' => $filters], '', true);
        $this->scartFinderNtdListWidget->bindToController();
        $this->scartFinderDomainruleListWidget = $this->widget->finder->makeList(['name' => 'domainrule', 'alias' => 'scartFinderDomainruleListWidget', 'filters' => $filters], '', true);
        $this->scartFinderDomainruleListWidget->bindToController();
    }

    public function onSearchPopUpScart() {
        return  $this->widget->finder->LoadFinderForm();
    }

    public function onSearchScart() {

        // searching
        $widgets = $this->widget->finder->onSearch();

        // init variables
        foreach (['input', 'ntd', 'domainrule'] as $type) {
            if (array_key_exists($type,$widgets)) {
                $this->widget->finder->vars[$type . 'finderresult'] = $widgets[$type];
            }
        }
        // make lists and view
        return ['.finderResults' =>  $this->widget->finder->showResults()];
    }



}
