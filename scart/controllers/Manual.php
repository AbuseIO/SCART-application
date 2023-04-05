<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use BackendMenu;
use Db;
use abuseio\scart\classes\helpers\scartLog;

class Manual extends scartController {
    public $requiredPermissions = ['abuseio.scart.manual_read'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'manual','chapter1');
    }

    public function listExtendQuery($query) {
    }

    public function preview($recordId, $context = null) {

        $menu = \abuseio\scart\models\Manual::find($recordId);
        if ($menu) {
            $this->pageTitle = 'Manual - ' . $menu->title;
            //scartLog::logLine("D-preview($recordId); chapter=$menu->chapter");
            BackendMenu::setContext('abuseio.scart', 'manual','chapter' . $menu->chapter);
            $sections = \abuseio\scart\models\Manual::where('deleted_at',null)->where('chapter',$menu->chapter)->get();
        } else {
            $sections = [];
        }
        $this->vars['sections'] = $sections ;
    }


}
