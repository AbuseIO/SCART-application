<?php namespace abuseio\scart\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Blockeddays extends Controller
{
    public $requiredPermissions = ['abuseio.scart.blocked_days'];

    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'Blockedday');
    }
}
