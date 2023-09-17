<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Systemconfig;
use Session;
use BackendMenu;
use Flash;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input;
use abuseio\scart\classes\iccam\scartExportICCAM;

class Changed extends scartController {

    public $requiredPermissions = ['abuseio.scart.changed'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Changed');
    }

    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        $query->where('status_code',SCART_STATUS_ABUSECONTACT_CHANGED);
    }

    public function onCheckNTD() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $record = Input::find($check);
            if ($record) {
                $record->workuser_id = scartUsers::getId();

                // check always and set default
                if ($record->classify_status_code != SCART_STATUS_SCHEDULER_CHECKONLINE && $record->classify_status_code != SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL)
                    $record->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;

                // log old/new for history
                $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$record->classify_status_code,"Changed; set by analist");

                $record->status_code = $record->classify_status_code;

                // reset error counters
                $record->browse_error_retry = $record->whois_error_retry = 0;

                $record->save();
                $record->logText("Manual set on $record->status_code");
                scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
            }
        }
        Flash::info('Checkonline (NTD) process started for selected url(s)');
        return $this->listRefresh();
    }

    public function onClose() {

        $checked = input('checked');

        if ($checked) {
            foreach ($checked AS $check) {
                $record = Input::find($check);
                if ($record) {
                    $record->workuser_id = scartUsers::getId();

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_CLOSE,"Changed; set by analist");

                    $record->status_code = SCART_STATUS_CLOSE;
                    $record->save();
                    $record->logText("Manual set on $record->status_code");

                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");

                    // ICCAM

                    if (scartICCAMinterface::isActive()) {

                        // check if valid ICCAM reportID
                        if (scartICCAMinterface::hasICCAMreportID($record->reference)) {

                            // get hoster counter -> can be outside NL (MOVE)
                            $country = '';
                            $abusecontact = Abusecontact::find($record->host_abusecontact_id);
                            if ($abusecontact) {
                                $country = $abusecontact->abusecountry;
                            }
                            // set on local when not filled
                            if (!$country) $country = Systemconfig::get('abuseio.scart::classify.hotline_country', '');;

                            // if not local then ICCAM moved action
                            if (!scartGrade::isLocal($country)) {

                                $reason = 'SCART content moved to country: '.$country;

                                // CLOSE with MOVED action

                                // ICCAM content moved (outside NL)
                                scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                    'record_type' => class_basename($record),
                                    'record_id' => $record->id,
                                    'object_id' => $record->reference,
                                    'action_id' => SCART_ICCAM_ACTION_MO,
                                    'country' => $country,
                                    'reason' => $reason,
                                ]);

                            }

                        }

                    }

                }
            }
            Flash::info('Selected url(s) set on closed');
        }
        return $this->listRefresh();
    }

    function getListRecords() {
        $listrecords = Session::get('changed_checkedrecords','');
        if ($listrecords) $listrecords = unserialize($listrecords);
        if (empty($listrecords)) $listrecords = [0];
        return ($listrecords);
    }
    function setListRecords($listrecords) {
        Session::put('changed_checkedrecords',serialize($listrecords));
    }


    /**
     * POLICE button
     *
     * Return question screen
     *
     * @return bool|mixed
     */
    public function onImagePolice() {

        scartLog::logLine("D-onImagePolice");

        $checked = input('checked');
        if ($checked) {

            $this->setListRecords($checked);
            $show_questions = $this->show_grade_questions();

        } else {
            Flash::warning('No record selected');
            $show_questions = false;
        }

        return $show_questions;
    }


    // ** SHOW POLICE CLASSIFICATION ** //

    /**
     * Setup screen with questions
     *
     * single=true
     * - record=item; show image
     *
     * single=false
     * - record=input; show no image;
     *
     * @param $questiongroup
     * @param $workuser_id
     * @param $single
     * @param $rec
     * @return mixed
     */
    public function show_grade_questions() {

        $questiongroup = SCART_GRADE_QUESTION_GROUP_POLICE;

        scartLog::logLine("D-show_grade_questions; questiongroup=$questiongroup");

        $questions = [];
        $grades  = Grade_question::where('questiongroup',$questiongroup)->orderBy('sortnr')->get();

        $toggle = true;
        foreach ($grades AS $grade) {

            $values = array();

            $question = new \stdClass();
            $question->type = $grade->type;
            $question->label = $grade->label;
            $question->name = $grade->name;
            $question->leftright = $grade->span;
            //$question->leftright = ($toggle) ? 'left' : 'right';
            $toggle = !$toggle;

            if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                $options = [];
                $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                foreach ($opts AS $opt) {
                    $option = new \stdClass();
                    $option->sortnr = $opt->sortnr;
                    $option->value = $opt->value;
                    $option->label = $opt->label;
                    $option->selected = '';
                    $options[] = $option;
                }
                $question->options = $options;

            } elseif ($question->type == 'text') {

                $question->value = $values;

            }

            $questions[] = $question;
        }

        $params = [
            'questiongroup' => $questiongroup,
            'questions' => $questions,
        ];

        $show_questions = $this->makePartial('show_grade_questions',$params);

        return $show_questions;
    }

    /**
     * Main function receiving SAVE question (answers)
     */
    public function onQuestionsSave() {

        $questiongroup = SCART_GRADE_QUESTION_GROUP_POLICE;
        $recordtype = SCART_INPUT_TYPE;

        scartLog::logLine("D-onQuestionsSave; questiongroup=$questiongroup ");

        // questions
        $grades  = Grade_question::where('questiongroup',$questiongroup)->orderBy('sortnr')->get();

        $listrecords = $this->getListRecords();
        $recs = scartGrade::getItemsOnIds($listrecords);

        foreach ($recs AS $item) {

            foreach ($grades AS $grade) {
                $inp = input($grade->name, '');
                $ans = Grade_answer::where('record_type',$recordtype)
                    ->where('record_id', $item->id)
                    ->where('grade_question_id', $grade->id)
                    ->first();
                if ($ans == '') {
                    $ans = new Grade_answer();
                    $ans->record_type = $recordtype;
                    $ans->record_id = $item->id;
                    $ans->grade_question_id = $grade->id;
                }

                scartLog::logLine("D-id=$item->id, question=$grade->name, ans=".print_r($inp,true) );

                // serialize -> multiselect values also
                $ans->answer = serialize($inp);
                $ans->save();
            }

            // log old/new for history
            $item->logHistory(SCART_INPUT_HISTORY_STATUS,$item->status_code,SCART_STATUS_FIRST_POLICE,"Changed; set by analist");

            $item->classify_status_code = $item->status_code = SCART_STATUS_FIRST_POLICE;
            $item->grade_code = SCART_GRADE_ILLEGAL;

            $item->logText("Set classify_status_code on: " . $item->classify_status_code . ", grade_code=" . $item->grade_code);
            $item->save();


        }

        Flash::info('Items classified');

        return $this->listRefresh();
    }

    // if form display

    public function onShowImage() {

        $id = input('id');
        $record = Input::find($id);
        if ($record) {
            $msgclass = 'success';
            $msgtext = 'Image loaded';
            $src = scartBrowser::getImageCache($record->url,$record->url_hash);
        } else {
            $msgclass = 'error';
            $msgtext = 'Image NOT found!?';
            scartLog::logLine("E-".$msgtext);
            $src = '';
        }
        $txt = $this->makePartial('show_image', ['src' => $src, 'msgtext' => $msgtext, 'msgclass' => $msgclass] );
        return ['show_result' => $txt ];
    }

}
