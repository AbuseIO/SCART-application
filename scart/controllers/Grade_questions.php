<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\helpers\scartLog;
use Backend\Classes\Controller;
use BackendMenu;

class Grade_questions extends scartController
{
    public $requiredPermissions = ['abuseio.scart.grade_questions'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\RelationController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'GradeQuestions');
    }

    public $iccamview = false;
    public function relationExtendViewWidget($widget, $field, $model) {

        if ($field == 'options') {
            scartLog::logLine("D-widget[$field]; iccam_field=".$model->iccam_field);
            if ($model->iccam_field != '') {
                $widget->showCheckboxes = false;
                $this->iccamview = true;
            } else {
                $widget->showCheckboxes = true;
                $this->iccamview = false;
            }
        }

    }

    public function relationExtendConfig($config, $field, $model)
    {
        // Make sure the model and field matches those you want to manipulate
        if ($field == 'options') {
            if ($model->iccam_field != '') {
                $config->manage['form'] = '$/abuseio/scart/models/grade_question_option/fields-iccamfield.yaml';
            } else {
                $config->manage['form'] = '$/abuseio/scart/models/grade_question_option/fields.yaml';
            }
        }
    }

}
