<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartImage;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input_extrafield;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_template;
use abuseio\scart\models\Systemconfig;
use Flash;
use BackendMenu;
use Response;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Input;
use Illuminate\Support\Facades\Redirect;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Parse\Bracket;
use Winter\Storm\Exception\ValidationException;
use abuseio\scart\Models\ImportWebform;

class Police extends scartController
{
    public $requiredPermissions = ['abuseio.scart.police'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $formConfig = 'config_form.yaml';

    public $sendLeaWidget = null;

    public function __construct() {

        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Police');

        $config = $this->makeConfig('$/abuseio/scart/models/ntdtemplate/fields_showlea.yaml');
        $config->model = new Ntd_template();
        $this->sendLeaWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        $this->sendLeaWidget->bindToController();
    }

    public function preview($recordId) {

        $form = $this->asExtension('FormController');

        $record = Input::find($recordId);
        if ($record && $record->status_code == SCART_STATUS_FIRST_POLICE) {
            $config = $form->getConfig();
            $config->form = '$/abuseio/scart/models/input/fields_viewfirstpolice.yaml';
        }

        return $form->preview($recordId);
    }


    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        $query->whereIn('status_code',[SCART_STATUS_FIRST_POLICE,SCART_STATUS_DIRECT_POLICE]);
    }

    public function hasFirstPolice() {

        return (Input::where('status_code',SCART_STATUS_FIRST_POLICE)->exists());
    }

    public function hasLea() {

        return (Systemconfig::get('abuseio.scart::options.lea_function',false));
    }

    public function onCheckNTD() {

        $checked = input('checked');
        if ($checked) {
            $directwarning = '';
            foreach ($checked AS $check) {
                $record = Input::find($check);
                if ($record && $record->status_code == SCART_STATUS_FIRST_POLICE) {

                    // reset online counter (direct NTD to ISP)
                    $record->online_counter = 0;

                    $record->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$record->classify_status_code,"Goto checkonline by analyst in POLICE function");

                    $record->status_code = $record->classify_status_code;
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                } else {
                    $directwarning = 'Some (or all) selected items cannot go to the checkonline status (only FIRST POLICE can) ';
                }
            }
            Flash::info('NTD process started for selected url(s)');
            if ($directwarning) Flash::warning($directwarning);
            return $this->listRefresh();
        } else {
            Flash::warning('No url(s) selected');
        }
    }

    public function onCheckNTDmanual() {

        $checked = input('checked');
        if ($checked) {
            $directwarning = '';
            foreach ($checked AS $check) {
                $record = Input::find($check);
                if ($record && $record->status_code == SCART_STATUS_FIRST_POLICE) {

                    // reset online counter (direct NTD to ISP)
                    $record->online_counter = 0;

                    $record->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL;

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$record->classify_status_code,"Goto checkonline by analyst in POLICE function");

                    $record->status_code = $record->classify_status_code;
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                } else {
                    $directwarning = 'Some (or all) selected items cannot go to the checkonline status (only FIRST POLICE can) ';
                }
            }
            Flash::info('NTD process started for selected url(s)');
            if ($directwarning) Flash::warning($directwarning);
            return $this->listRefresh();
        } else {
            Flash::warning('No url(s) selected');
        }
    }

    public function onClose() {

        $checked = input('checked');
        if ($checked) {
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {
                    $this->closePolice($record);
                }
            }
            Flash::info('Selected url(s) set on closed');
            return $this->listRefresh();
        } else {
            Flash::warning('No url(s) selected');
        }
    }

    function closePolice($record,$loghistory='Close by analyst in POLICE function',$logtext='Manual set on close',$status_code=SCART_STATUS_CLOSE) {

        // log old/new for history
        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$status_code,$loghistory);
        $record->status_code = $status_code;
        $record->save();
        $record->logText($logtext);

        scartLog::logLine("D-ClosePolice; filenumber=$record->filenumber, grade=$record->grade_code, url=$record->url, set on $record->status_code");

        if ($record->grade_code == SCART_GRADE_ILLEGAL) {

            // check if ICCAM active (for this record)

            if (scartICCAMinterface::isActive()) {
                if (scartICCAMinterface::hasICCAMreportID($record->reference)) {
                    // get hoster
                    $abusecontact = Abusecontact::find($record->host_abusecontact_id);
                    if ($abusecontact) {
                        $country = $abusecontact->abusecountry;
                        // check if hoster local
                        if (scartGrade::isLocal($country)) {
                            // 2026/2/26/Gs: Changed from CR to CU
                            // local -> then content unavailable
                            $action = SCART_ICCAM_ACTION_CU;
                            $reason = 'ContentNotFound';
                        } else {
                            // not local -> content moved (MO)
                            $action = SCART_ICCAM_ACTION_MO;
                            $reason = 'SCART content moved to '.$country;
                        }
                        $record->logText("POLICE function set CLOSE illegal content; inform ICCAM about '$reason'");
                        scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($record),
                            'record_id' => $record->id,
                            'object_id' => $record->reference,
                            'action_id' => $action,
                            'country' => $country,
                            'reason' => $reason,
                        ]);
                    }
                }
            }

        }
    }

    public function onCheckedSend() {

        $checked = input('checked');
        if ($checked) {

            //$config = $this->makeConfig('$/abuseio/scart/models/input/fields_showlea.yaml');
            //$config->model = Input::find($checked[0]);
            //$this->sendLeaWidget = $this->makeWidget('Backend\Widgets\Form', $config);

            $prm = [
                'sendLeaWidget' => $this->sendLeaWidget,
                'recordIds' => implode('#',$checked),
            ];
            $show_send_lea = $this->makePartial('show_lea_send',$prm);

        } else {
            $show_send_lea = '';
        }

        return $show_send_lea;
    }

    public function onSendLea() {

        $recordIds = input('recordIds');
        $abusecontactId = input('_lea_abusecontact');
        $ntd_note = input('ntd_note');
        scartLog::logDump("D-onSendLea; abusecontact=".$abusecontactId.", recordIds=",$recordIds);

        scartLog::logLine("D-onSendLea; create LEA (POLICE) NTD's");

        $warning = '';
        $records = explode('#',$recordIds);
        foreach ($records as $recordId) {
            $record = Input::find($recordId);
            if ($record) {
                $ntd = Ntd::createNTDurl($abusecontactId, $record, SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE, 0, SCART_NTD_ABUSECONTACT_TYPE_POLICE);
                if ($ntd) {

                    $record->ntd_note = $ntd_note;

                    if (ImportWebform::isGeneratedUrl($record->url)) {
                        // stop & close
                        $this->closePolice($record,'Send to LEA contact and closed by analyst',"Added to NTD '{$ntd->filenumber}' - status set on CLOSE");
                    } else {
                        // real url - check online
                        $status_code = SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL;
                        // log old/new for history
                        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$status_code,'Send to LEA by analyst in POLICE function');
                        $record->status_code = $status_code;
                        $record->save();
                        $record->logText('Set on manual checkonline');
                    }

//                    //TESTING!
//                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_CLOSE,"Send to LEA contact and closed by analyst");
//                    $record->status_code = SCART_STATUS_CLOSE;
//                    $record->save();
//                    $record->logText("Added to NTD '{$ntd->filenumber}' - status set on CLOSE");
                } else {
                    $warning = 'LEA contact NOT ready to receive messages - local country and/or GDPR not set?';
                    break;
                }
            }
        }

        if ($warning) {
            Flash::warning($warning);
            scartLog::logLine("W-onSendLea; warning: $warning");
        }

        return Redirect::refresh();
    }

    public function onClassifY() {

        $show_questions = $warning = '';

        $checked = input('checked');
        if ($checked) {

            $questions = [];

            scartLog::logLine("D-onClassifY; show ILLEGAL questions");

            // verify if no FIRST POLICE
            $firstRecord = false;
            foreach ($checked as $recordId) {
                $record = Input::find($recordId);
                scartLog::logLine("recordId=$recordId, status=".$record->status_code);
                if ($record && ($record->status_code != SCART_STATUS_DIRECT_POLICE)) {
                    $warning = 'Cannot reclassify FIRST POLICE reports - please deselect';
                }
                if (!$firstRecord) $firstRecord = $record;
            }

            if (!$warning && $firstRecord) {

                // get all illegal questions
                $grades = Grade_question::getClassifyQuestions(SCART_GRADE_QUESTION_GROUP_POLICE, SCART_URL_TYPE_IMAGEURL);

                foreach ($grades AS $grade) {

                    $value = Grade_answer::where('record_type',$firstRecord->url_type)->where('record_id',$firstRecord->id)->where('grade_question_id',$grade->id)->first();
                    $values = ($value) ? unserialize($value->answer) : '';

                    $question = new \stdClass();
                    $question->type = $grade->type;
                    $question->required = $grade->required;
                    $question->label = $grade->label;
                    $question->name = $grade->name;
                    $question->leftright = $grade->span;

                    if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                        if ($values=='') $values = array();

                        $options = [];
                        $opts = Grade_question_option::where('grade_question_id', $grade->id)->orderBy('sortnr')->get();
                        foreach ($opts AS $opt) {
                            $option = new \stdClass();
                            $option->sortnr = $opt->sortnr;
                            $option->value = $opt->value;
                            $option->label = $opt->label;
                            $option->selected = (in_array($option->value, $values) ? 'selected' : '');
                            $options[] = $option;
                        }
                        $question->options = $options;

                    } elseif ($question->type == 'text') {
                        $question->value = (is_array($values) ? implode(' ',$values) : $values);
                    }

                    $questions[] = $question;
                }

                $prm = [
                    'recordIds' => implode('#',$checked),
                    'questions' => $questions,
                ];

                $show_questions = $this->makePartial('show_lea_classify',$prm);

            }

        } else {
            $warning = 'No records selected';
        }

        if ($warning) {
            scartLog::logLine("W-onClassifYSave; warning: $warning");
            throw new ValidationException(['status' => $warning]);
        } else {
            return $show_questions;
        }
    }

    public function onClassifYSave() {

        $recordIds = input('recordIds');

        scartLog::logDump("D-onClassifYSave; save answers");

        $warning = '';
        $records = explode('#',$recordIds);

        $grades = Grade_question::getClassifyQuestions(SCART_GRADE_QUESTION_GROUP_POLICE, SCART_URL_TYPE_IMAGEURL);

        foreach ($records as $recordId) {
            $record = Input::find($recordId);
            if ($record) {
                // remove always (possible) existing answers
                Grade_answer::where('record_type',$record->url_type)
                    ->where('record_id', $record->id)
                    ->delete();
                foreach ($grades AS $grade) {
                    $inp = input($grade->name, '');
                    if ($grade->requred && empty($inp)) {
                        throw new ValidationException([$grade->name => 'Field is required']);
                    }
                    $ans = new Grade_answer();
                    $ans->record_type = $record->url_type;
                    $ans->record_id = $record->id;
                    $ans->grade_question_id = $grade->id;
                    $ans->answer = serialize($inp);
                    $ans->save();
                }

                if ($record->grade_code == SCART_GRADE_UNSET) {
                    $record->grade_code = SCART_GRADE_ILLEGAL;
                    $record->save();
                    $record->logText("Set classify (grade) on: " . $record->grade_code);
                }

            }
        }

        if ($warning) {
            Flash::warning($warning);
            scartLog::logLine("W-onClassifYSave; warning: $warning");
        } else {
            Flash::info('Selected report(s) classified illegal');
        }

        return Redirect::refresh();
    }

    // OBSOLUTE?

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


    public function onGetPasswordField() {

        $input_id = input('input_id');
        $field_label = input('field_label');
        $password = input('password');
        scartLog::logLine("D-onGetPasswordField; input_id=$input_id, field_label=$field_label, password=$password");
        if (!($decrypted = Input_extrafield::getDecryptedExtraField($input_id,$field_label,$password))) {
            Flash::warning('Password not valid');
        }
        return $decrypted;
    }



}
