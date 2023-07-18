<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\scheduler\scartSchedulerVerifyChecker;
use abuseio\scart\models\Input;
use abuseio\scart\widgets\Dropdown;
use BackendMenu;
use BackendAuth;
use Config;
use Lang;
use Session;
use Redirect;
use abuseio\scart\classes\helpers\scartExportICCAM;
use abuseio\scart\classes\base\scartController;
use Db;
use Exception;

class Input_verify extends scartController {

    public $workuser_id;

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'abuseio\scart\Behaviors\GradeController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $gradeConfig = 'config_grade.yaml';

    public $formWidget = null;

    public function __construct() {
        parent::__construct();
        $this->initVerify();

        // Note: check if below is really needed
        $config = $this->makeConfig('$/abuseio/scart/models/input_verify/fields.yaml');
        $config->model = new \abuseio\scart\models\Input_verify();
        $this->formWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        $this->formWidget->alias = 'formWidget';
        // following wintercms docs bindToController is needed with __construct
        $this->formWidget->bindToController();

    }

    /**
     * Init variable, like the current user and widget.
     */
    private function initVerify()
    {
        scartLog::logLine("D-initVerify; action=$this->action");
        BackendMenu::setContext('abuseio.scart', 'Verify', $this->action);
        $user               = BackendAuth::getUser();
        $this->pageTitle    = 'Verify';
        $this->workuser_id  = $user->id;
    }


    /**
     * @description Load de the verify page with a toolbar and form
     */
    public function verifyitem($recordId=0)
    {

        $toolbar = $this->makeWidget('Backend\Widgets\Toolbar', []);
        $toolbar->buttons = 'toolbar';
        $this->vars['toolbar'] = $toolbar;

        $config = $this->makeConfig('$/abuseio/scart/models/input_verify/fields.yaml');

        // get new ones or already taken (busy with)
        //$input_ids = \abuseio\scart\models\Input_verify::getAlreadyVerifiedItemsIdsByUser($this->workuser_id);
        // put query of exclusion done records into db itself else a very big array when the database gets bigger
        //trace_sql();
        $config->model = $this->vars['record'] = \abuseio\scart\models\Input_verify::whereIn('workuser_id', [0,$this->workuser_id])
            ->whereIn('status', [SCART_VERIFICATION_VERIFY])
            ->whereNotExists(function($query) {
                // exclude not done before
                $query->select(Db::raw(1))
                    ->from('abuseio_scart_input_verify AS iv')
                    ->where('iv.status','<>',SCART_VERIFICATION_VERIFY)
                    ->whereRaw('iv.input_id=abuseio_scart_input_verify.input_id')
                    ->where('iv.workuser_id',$this->workuser_id);
            })
            //->whereNotIn('input_id', $input_ids)
            ->orderBy('updated_at','DESC')
            ->first();

        if ($config->model) {
            try {
                scartLog::logLine("D-Found verification items - make widget");
                $this->formWidget = $this->makeWidget('Backend\Widgets\Form', $config);

                if ($config->model->workuser_id == 0) {
                    // set working so no conflict with other workuser
                    $config->model->workuser_id = $this->workuser_id;
                    $config->model->save();
                }

            } catch (Exception $e) {
                scartLog::logLine("E-Input_verify(verifyItem): Error: ". $e->getMessage());
            }

        } else {
            scartLog::logLine('D-No Verify item available');
            return trans('abuseio.scart::lang.backend.list.no_records');
        }

        $this->vars['form'] = $this->formWidget;
    }

    /**
     * @description: show only the items of the user
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        //$query->whereNotIn('status', [SCART_VERIFICATION_VALIDATE, SCART_VERIFICATION_VERIFY, SCART_VERIFICATION_DONE])
        $query->whereIn('status', [SCART_VERIFICATION_FAILED,SCART_VERIFICATION_COMPLETE])
            ->select('input_id','status')->distinct();
    }

    /**
     * @Description View larger image for te list behavior
     * @return view imagepopup.htm..
     */
    public function onLoadImage()
    {
        $record = Input::find(post('id'));
        return $this->makePartial('imagepopup', ['record' => $record, 'img' => scartBrowser::getImageCache($record->url,$record->url_hash)]);
    }


}
