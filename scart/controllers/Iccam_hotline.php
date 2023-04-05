<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use Backend\Classes\Controller;
use BackendMenu;

class Iccam_hotline extends scartController
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'Iccam_hotline');
    }
}
