<?php namespace ReporterTool\EOKM\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Manualedit extends Controller {

    public $requiredPermissions = ['reportertool.eokm.manual_write'];

    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'utility', 'Manualedit');
    }
}
