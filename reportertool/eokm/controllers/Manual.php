<?php namespace ReporterTool\EOKM\Controllers;

use reportertool\eokm\classes\ertController;
use BackendMenu;
use Db;
use reportertool\eokm\classes\ertLog;

class Manual extends ertController {
    public $requiredPermissions = ['reportertool.eokm.manual_read'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'manual','chapter1');
    }

    public function listExtendQuery($query) {
    }

    public function preview($recordId, $context = null) {

        $menu = \ReporterTool\EOKM\Models\Manual::find($recordId);
        $this->pageTitle = 'Manual - ' . $menu->title;
        //ertLog::logLine("D-preview($recordId); chapter=$menu->chapter");
        BackendMenu::setContext('ReporterTool.EOKM', 'manual','chapter' . $menu->chapter);
        $sections = \ReporterTool\EOKM\Models\Manual::where('deleted_at',null)->where('chapter',$menu->chapter)->get();
        $this->vars['sections'] = $sections ;
    }


}
