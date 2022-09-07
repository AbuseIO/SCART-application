<?php namespace abuseio\scart\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Addon;

class Addons extends Controller
{
    public $requiredPermissions = ['abuseio.scart.system_addons'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'addons');
    }


    /**
     * check dynamic if abuseio\scart\addon\<codename> avaliable is -> if yes, then classexists=true
     *
     * valid
     *
     *
     */

    public function onCheckValidate() {

        $cntok = $cntno = 0;

        $checked = input('checked');
        foreach ($checked AS $id) {

            $addon = Addon::find($id);
            if ($addon) {

                $valid = $addon->checkValidate();

                $cntok += ($valid) ? 1: 0;
                $cntno += (!$valid) ? 1: 0;


            }

        }

        Flash::info("$cntok addon(s) exists/valid, $cntno addon(s) not");

        return $this->listRefresh();
    }




}
