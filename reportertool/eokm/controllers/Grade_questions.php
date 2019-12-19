<?php namespace ReporterTool\EOKM\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Grade_questions extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\RelationController',
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    /**
     * @var array Permissions required to view this page.
     */
    public $requiredPermissions = ['reportertool.eokm.grade_questions'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'utility', 'Grade_questions');
    }
}
