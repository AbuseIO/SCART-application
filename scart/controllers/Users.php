<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\helpers\scartLog;
use Backend\Classes\Controller;
use BackendMenu;
use abuseio\scart\models\User;

class Users extends scartController
{
    public $requiredPermissions = ['abuseio.scart.user_write'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'Users');
    }

    function min2time($mins) {
        return sprintf('%02d:%02d',floor($mins/ 60),($mins % 60));
    }

    public function update($recordId, $context=null) {

        $user = User::find($recordId);
        $workschedule = [];
        for ($i=0;$i<7;$i++) {
            if (isset($user->workschedule[$i])) {
                if (isset($user->workschedule[$i][0])) {
                    $workschedule[$i][0] = $this->min2time($user->workschedule[$i][0]);
                }
                if (isset($user->workschedule[$i][1])) {
                    $workschedule[$i][1] = $this->min2time($user->workschedule[$i][1]);
                }

            } else {
                $workschedule[$i] = [];
            }

        }
        $this->vars['workschedule'] = $workschedule;
        //trace_log($user->workschedule);

        return $this->asExtension('FormController')->update($recordId, $context);
    }

    public function formBeforeUpdate ($model) {
        scartLog::logLine('D-formBeforeUpdate; force beforeUpdate');
        // set workschedule always so beforeUpdate is called
        $model->workschedule = 'e';
        $model->save();
    }


}
