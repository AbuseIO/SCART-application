<?php

namespace abuseio\scart\Behaviors;

use Redirect;
use Flash;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartImage;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_verify;

class GradeController extends \Winter\Storm\Extension\ExtensionBase
{
    protected $controller;

    public $gradeConfig = 'config_grade.yaml';

    public function __construct($controller)
    {
        $this->controller = $controller;
        $this->config = $this->controller->makeConfig($controller->gradeConfig);
    }

    /**
     * @param bool $formquestions
     * @return array|bool|mixed
     */
    public function onImageIllegal($formquestions = false)
    {

        $formquestions = false;

        $isPopup = filter_var(input('popup', true), FILTER_VALIDATE_BOOLEAN);
        $record_id = input('record_id');
        scartLog::logLine("D-onImageIllegal; record_id=$record_id");

        // dynamic: used in Grade and Input Verify
        $record = new $this->config->modelClass();
        $record = $record->find($record_id);

        if ($record) {
            $formquestions = $this->getGradeQuestions(SCART_GRADE_QUESTION_GROUP_ILLEGAL, scartUsers::getId(), true, $record);
        } else {
            scartLog::logLine("D-onImageIllegal; no record found with id=$record_id");
            Flash::error("Unknown (image)!?");
        }

        return ($isPopup) ? $formquestions : ['#Verifycontainer' => $formquestions];

    }

    /**
     * @param string $html
     * @return array|mixed
     */
    public function onImageNotIllegal($html = '', $formquestions ='')
    {

        $isPopup = filter_var(input('popup', true), FILTER_VALIDATE_BOOLEAN);
        $record_id = input('record_id');

        scartLog::logLine("D-onImageNotIllegal; record_id=$record_id");

        // dynamic: used in Grade and Input Verify
        $record = new $this->config->modelClass();
        $record = $record->find($record_id);

        if ($record) {
            $formquestions = $this->getGradeQuestions(SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL, $this->controller->workuser_id, true, $record);
        } else {
            scartLog::logLine("D-onImageNotIllegal; no record found with id=$record_id");
        }

        return ($isPopup) ? $formquestions : ['#Verifycontainer' => $formquestions];

    }

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
    public function getGradeQuestions($questiongroup, $workuser_id, $single, $rec)
    {

        // When input is a relation of this model..
        $input = (!$this->config->InputIsMain) ? $rec->input : $rec;

        if ($single) {
            $imgsize = scartImage::getImageSizeAttr($input, 250);
        }

        // $rec is first from items selected
        $answer_record_id = $rec->id;

        // get record type
        $recordtype = ($this->config->InputIsMain) ? SCART_INPUT_TYPE : SCART_INPUT_TYPE_VERIFY;
        scartLog::logLine("D-show_grade_questions; answer_record_id=$answer_record_id, questiongroup=$questiongroup, single=$single, recordtype=$recordtype");

        $gradeitems = ($input->url_type == SCART_URL_TYPE_MAINURL) ? 'INPUT' : (($input->url_type == SCART_URL_TYPE_VIDEOURL) ? 'VIDEO' : 'IMAGE');
        $gradeitems .= (!$single) ? 'S' : '';

        if ($answer_record_id) {

            $questions = [];

            // questions depending on url_type
            $grades = Grade_question::getClassifyQuestions($questiongroup, $input->url_type);

            $toggle = true;
            foreach ($grades AS $grade) {

                $value = Grade_answer::where('record_type', $recordtype)->where('record_id', $answer_record_id)->where('grade_question_id', $grade->id)->first();
                $values = ($value) ? unserialize($value->answer) : '';
                if ($values == '') $values = array();
                //scartLog::logLine("D-show_grade_questions; question=$grade->name, type=$grade->type, values=" . implode(',', $values));

                $question = new \stdClass();
                $question->type = $grade->type;
                $question->label = $grade->label;
                $question->name = $grade->name;
                $question->leftright = $grade->span;
                //$question->leftright = ($toggle) ? 'left' : 'right';
                $toggle = !$toggle;

                if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

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
                    $question->value = $values;
                }

                $questions[] = $question;
            }

            // if single and url set then show image
            $src = ($single) ? scartBrowser::getImageCache($input->url, $input->url_hash) : '';

            $gradeheader = (($questiongroup == SCART_GRADE_QUESTION_GROUP_ILLEGAL) ? 'ILLEGAL' :
                (($questiongroup == SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL) ? 'NOT ILLEGAL' : 'FIRST POLICE'));

            $params = [
                'gradeitems' => $gradeitems,
                'gradeheader' => $gradeheader,
                'single' => $single,
                'record_id' => $rec->id,
                'recordtype' => $recordtype,
                'workuser_id' => $workuser_id,
                'questiongroup' => $questiongroup,
                'src' => $src,
                'imgsize' => (($single) ? $imgsize : ''),
                'questions' => $questions,
            ];

            $view = (isset($this->config->viewTemplate)) ? $this->config->viewTemplate : '$/abuseio/scart/behaviors/gradecontroller/questions.htm';

            $show_questions = $this->controller->makePartial($view, $params);

        } else {

            scartLog::logLine("D-show_grade_questions; NO ITEMS (MORE) SELECTED!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    /**
     * @return mixed
     */
    public function onVerifiedSave() {

        // first save; if not valid then id=0
        if ($id = $this->onQuestionsSave()) {

            $model = Input_verify::find($id);
            if($model) {
                if ($int = $this->isverifiedItemsEqual($model)) {
                    if ($int >= $this->config->reviewRepeat ) {
                        $model->setComplete();
                    } else {
                        $model->insertVerify();
                    }
                } else {
                    $model->setFailed();
                }

            }

        }

        return Redirect::refresh();
    }


    /**
     * @param $model
     * @param bool $bool
     * @return bool|int|void
     */
    public function isverifiedItemsEqual($model, $bool = false)
    {

        // $this->config->compare can be set on 'grade_code'; when not then all answers must be equal

        // get verify records with same input_id
        $verifyList = Input_verify::where('input_id', $model->input_id)->get();
        $equalcount = count($verifyList);
        scartLog::logLine("D-isverifiedItemsEqual; count(verifylist)=$equalcount");
        if ($equalcount > 1) {
            // we got 2 or more
            $lastanswers = '';
            foreach ($verifyList as $item) {

                if ($lastanswers == '') {
                    // save first one
                    if ($this->config->compare == 'grade_code') {
                        $lastanswers = $item->grade_code;
                    } else {
                        $lastanswers = $item->grade_code.$this->bundleAnswers($item->answers); // Bundle also the answers for the comparing
                    }
                } else {
                    if ($this->config->compare == 'grade_code') {
                        $answers = $item->grade_code;
                    } else {
                        $answers = $item->grade_code.$this->bundleAnswers($item->answers); // Bundle also the answers for the comparing
                    }
                    // compare if the same
                    if ($lastanswers != $answers) {
                        $equalcount = 0;
                        scartLog::logLine("D-isverifiedItemsEqual; answer(s) not equal ('$lastanswers' != '$answers')");
                        break;
                    }

                }
            }
        }

        /*
        $answers = Grade_answer::where([['record_id', $input_id], ['record_type', SCART_INPUT_TYPE]])->get();
        $class = new \stdClass();

        $class->answersjson = ($this->config->compare == 'grade_code') ? $item->grade_code : $input->grade_code.$this->bundleAnswers($answers);
        $index[] = $class;
        return ($this->isEquals($index)) ? count($index) : false;
        */

        return $equalcount;
    }


    /**
     * @param $items
     * @param bool $return
     * @return bool
     */
    private function isEquals($items, $return = true) {

        $anotherItemsArray = $items;

        if(count($items) > 1) {
            foreach ($items as $item) {
                foreach ($anotherItemsArray as $anotheritem) {
                    if ($item == $anotheritem) {
                        $return = true;
                    }else {
                        $return = false;
                        break;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Description: Main function receiving SAVE question (answers)
     *
     * single=true
     * - set answer for item or input
     *
     * single=false
     * - set answer for selected items
     *
     * @return array
     */
    public function onQuestionsSave()
    {
        $single = input('single');

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        $questiongroup = input('questiongroup');

        // get record type
        $recordtype = ($this->config->InputIsMain) ? SCART_INPUT_TYPE : SCART_INPUT_TYPE_VERIFY;

        scartLog::logLine("D-onQuestionsSave; single=$single, record_id=$record_id, recordtype=$recordtype ");

        // set buttons
        $setbuts = [];
        $showresult = '';

        if ($single) {
            $rec = new \stdClass();
            $rec->input_id = $record_id;
            $recs = array($rec);
        } else {
            $listrecords = $this->getListRecords();
            $recs = scartGrade::getSelectedItems($workuser_id, $listrecords);
        }

        foreach ($recs AS $rec) {

            // set select off
            scartGrade::setGradeSelected($workuser_id, $rec->input_id, false);

            if ($recordtype == SCART_INPUT_TYPE) {
                $input = $item = Input::find($rec->input_id);
            } elseif ($recordtype == SCART_INPUT_TYPE_VERIFY) {
                $input = Input_verify::find($rec->input_id);
                $item = $input->input;
            }

            // questions depending on url_type
            $grades = Grade_question::getClassifyQuestions($questiongroup, $item->url_type);

            foreach ($grades AS $grade) {

                if ($grade->type != 'section') {

                    $inp = input($grade->name, '');

                    if ($inp=='' && $recordtype == SCART_INPUT_TYPE_VERIFY) {
                        // all fields are required -> flash if empty!
                        Flash::error('Question '.$grade->label.' must be filled');
                        return 0;
                    }

                    $ans = Grade_answer::where('record_type', $recordtype)
                        ->where('record_id', $input->id)
                        ->where('grade_question_id', $grade->id)
                        ->first();
                    if ($ans == '') {
                        $ans = new Grade_answer();
                        $ans->record_type = $recordtype;
                        $ans->record_id = $input->id;
                        $ans->grade_question_id = $grade->id;
                    }
                    // serialize -> multiselect values also
                    $ans->answer = serialize($inp);
                    $ans->save();
                    //scartLog::logLine("D-Save question '$grade->name' (id=$grade->id), record_id=$item->id, answer=$ans->answer");

                }
            }

            if ($questiongroup == SCART_GRADE_QUESTION_GROUP_ILLEGAL) {
                $input->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;
                $input->grade_code = SCART_GRADE_ILLEGAL;
            } elseif ($questiongroup == SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL) {
                $input->classify_status_code = SCART_STATUS_CLOSE;
                $input->grade_code = SCART_GRADE_NOT_ILLEGAL;
            } elseif ($questiongroup == SCART_GRADE_QUESTION_GROUP_POLICE) {
                $input->classify_status_code = SCART_STATUS_FIRST_POLICE;
                $input->grade_code = SCART_GRADE_ILLEGAL;
            }

            if ($recordtype == SCART_INPUT_TYPE) {
                $input->logText("Set classify_status_code on: " . $item->classify_status_code . ", grade_code=" . $input->grade_code);
            } else {
                unset($input->classify_status_code); // The  table input_verify  doesnt have the column classify_status_code
                $input->status = SCART_VERIFICATION_VALIDATE;
                $input->workuser_id = $workuser_id;
                $input->logText("Set grade_code=" . $input->grade_code);
            }

            $input->save();
            if ($recordtype == SCART_INPUT_TYPE) {
                $setbutton = $this->setButtons($item, $workuser_id, false);
                $setbutton['hash'] = $item->filenumber;
                $setbuts[] = $setbutton;
            }


        }

        if ($recordtype == SCART_INPUT_TYPE) {
            if ($this->getShowHide() != '') {
                $showresult .= $this->makePartial('js_refreshscreen');
            } else {
                $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => true]);
                if (!$single) {
                    Flash::info('Items classified');
                }
            }

            return ['show_result' => $showresult];

        } else {
            return $record_id;
        }

    }


    /***
     * @param $item
     * @return string
     */
    private function bundleAnswers($item, $tst = '') {

        $toArray = $item->toArray();

        foreach ($toArray as $key => $answer) $tst .= $answer['grade_question_id'].''.$answer['answer'];

        return $tst;
    }

}
