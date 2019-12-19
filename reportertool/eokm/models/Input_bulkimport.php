<?php

namespace ReporterTool\EOKM\Models;

/**
 * Input_bulkimport
 *
 * Import of images (notifications) with grading.
 *
 * 20-nov-2019; not used anymore, may be of later use
 *
 */

use ApplicationException;
use Backend\Models\ImportModel;
use reportertool\eokm\classes\ertModel;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_input;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Input_status;
use ReporterTool\EOKM\Models\Input_source;
use ReporterTool\EOKM\Models\Input_type;

class Input_bulkimport extends ImportModel {

    use \October\Rain\Database\Traits\Validation;

    public $rules = [];

    protected $required = [
        'url',
        'source_code',
        'type_code',
        'workuser_id',
    ];

    protected $fillable = [
        'url',
        'reference',
        'url_referer',
        'source_code',
        'type_code',
        'status_code',
        'workuser_id',
    ];

    protected $importline_required = [
        'url',
        'host_owner',
        'host_country',
        'registrar_owner',
        'registrar_country',
        'firstseen_at',
        'online_counter',
        'lastseen_at',
    ];

    private $_grade_answers = [];

    public function importData($results, $sessionKey = null) {

        //trace_log(input ('ImportOptions', ''));

        // Transfer & validate input fields

        foreach ($this->required AS $field) {
            if ($this->$field === '') {
                throw new ApplicationException("Field $field is required");
                return;
            }
        }

        // add grade question fields
        $importOptions = input('ImportOptions', []);
        $grades  = Grade_question::where('questiongroup','illegal')->orderBy('sortnr')->get();
        $this->gradeanswers = [];
        foreach ($grades AS $grade) {
            $field = $grade->name;
            $answer = (isset($importOptions[$field])) ? $importOptions[$field] : '';
            $this->_grade_answers[$field] = $answer;
            if ($grade->type=='select' && $answer=='') {
                // required
                throw new ApplicationException("Field $field is required");
                return;
            }
        }

        // walk importlines

        $first = true;
        $rowindex = 0;
        $input = '';
        foreach ($results as $row => $data) {

            try {

                $rowindex += 1;

                ertLog::logLine("D-Import: rowindex=$rowindex; " . print_r($data, true));

                // validate if min fields are set
                $errortxt = '';
                foreach ($this->importline_required AS $importfield) {
                    if (!isset($data[$importfield]) || $data[$importfield]=='') {
                        if ($errortxt) $errortxt .= ',';
                        $errortxt .= $importfield;
                    }
                }

                if ($errortxt!='') {

                    $this->logError($row, "Field(s) '$errortxt' missing (or empty)");

                } else {

                    if ($first) {

                        // create/update input record
                        $input = Input::where('url', $this->url)
                            ->where('deleted_at',null)
                            ->first();
                        if ($input=='') {
                            $input = new Input();
                            $logtext = "bulkimport input create";
                        } else {
                            $logtext = "bulkimport input update";
                        }
                        foreach ($this->fillable AS $fill) {
                            $input->$fill = $this->$fill;
                        }
                        $input->status_code = ERT_STATUS_CLOSE;
                        $input->save();
                        $input->logText($logtext);

                        $first = false;
                    }

                    //ertLog::logLine("D-Step 1" );
                    $notification = Notification::where('url', $data['url'])
                        ->where('deleted_at',null)
                        ->first();
                    if ($notification=='') {
                        $notification = new Notification();
                        $this->logCreated();
                        $logtext = "bulkimport notification created";
                    } else {
                        $this->logUpdated();
                        $logtext = "bulkimport notification updated";
                    }
                    //ertLog::logLine("D-Step 2" );
                    //$notification->fill($data);

                    // copy data with date conversion
                    foreach ($data AS $fld => $val) {
                        if ($fld=='firstseen_at' || $fld=='lastseen_at') {
                            $notification->$fld = date('Y-m-d H:i:s', strtotime($val));
                        } else {
                            $notification->$fld = $val;
                        }
                    }
                    $notification->type_code = ERT_TYPE_CODE_DEFAULT;
                    $notification->grade_code = ERT_GRADE_ILLEGAL;
                    $notification->status_code = ERT_STATUS_CLOSE;
                    $notification->reference = $input->reference;
                    $notification->save();
                    $notification->logText($logtext);

                    //ertLog::logLine("D-Step 3" );
                    $notinp = Notification_input::where('input_id', $input->id)
                        ->where('notification_id', $notification->id)
                        ->first();
                    ertLog::logLine("D-Step 4" );
                    if ($notinp=='') {
                        $notinp = new Notification_input();
                        $notinp->input_id = $input->id;
                        $notinp->notification_id = $notification->id;
                        $notinp->save();
                        $notinp->logText("bulkimport notification_input created");
                    }

                    foreach ($grades AS $grade) {
                        $ans = Grade_answer::where('record_type',ERT_NOTIFICATION_TYPE)
                            ->where('record_id',$notinp->notification_id)
                            ->where('grade_question_id',$grade->id)->first();
                        if ($ans=='') {
                            $ans = new Grade_answer();
                            $ans->record_type = ERT_NOTIFICATION_TYPE;
                            $ans->record_id = $notinp->notification_id;
                            $ans->grade_question_id = $grade->id;
                        }
                        // serialize -> multiselect values also
                        $ans->value = serialize($this->_grade_answers[$grade->name]);
                        $ans->save();
                    }

                }

            }
            catch (\Exception $ex) {

                ertLog::logLine("E-importData; error=" . $ex->getMessage());

                $this->logError($row, $ex->getMessage());

                throw new ApplicationException($ex->getMessage());
                return;

            }

        }

    }

    // @To-do; lang select
    public function getStatusCodeOptions($value,$formData) {

        $recs = Input_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    // @To-do; lang select
    public function getSourceCodeOptions($value,$formData) {

        $recs = Input_source::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getTypeCodeOptions($value,$formData) {

        $recs = Input_type::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getWorkuserIdOptions($value,$formData) {
        $model = new ertModel();
        return $model->getGeneralWorkuserIdOptions($value,$formData);
    }


    public static function getImportGradeQuestions() {

        $questions = [];
        $grades  = Grade_question::where('questiongroup','illegal')->orderBy('sortnr')->get();

        $toggle = true;
        foreach ($grades AS $grade) {

            $values = array();

            $question = new \stdClass();
            $question->type = $grade->type;
            $question->label = $grade->label;
            $question->name = $grade->name;
            $question->leftright = $grade->span;
            $toggle = !$toggle;

            if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                $options = [];
                $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                foreach ($opts AS $opt) {
                    $option = new \stdClass();
                    $option->sortnr = $opt->sortnr;
                    $option->value = $opt->value;
                    $option->label = $opt->label;
                    $option->selected =  (in_array($option->value,$values) ? 'selected' : '');
                    $options[] = $option;
                }
                $question->options = $options;

            } else {

                $question->value = $values;

            }

            $questions[] = $question;
        }

        return $questions;
    }


}
