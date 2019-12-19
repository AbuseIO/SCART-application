<?php namespace ReporterTool\EOKM\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Input_status;
use ReporterTool\EOKM\Models\Rule_type;

class Domainrule extends Controller
{
    public $requiredPermissions = ['reportertool.eokm.rules'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
    }


    public function listFilterExtendScopes($filter) {

        $recs = Rule_type::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $options = array();
        foreach ($recs AS $rec) {
            $options[$rec->code] = $rec->title . ' - ' . $rec->description;
        }

        $filter->addScopes([
            'type_code' => [
                'label' => 'type',
                'type' => 'group',
                'conditions' => 'type_code in (:filtered)',
                'options' => $options,
                'default' => $options,
            ],
        ]);

    }

}
