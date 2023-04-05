<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;
use abuseio\scart\classes\iccam\api3\models\ScartICCAMapi;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Redirect;

class Iccam_api_field extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController'    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'Iccam_api_field');
    }



}
