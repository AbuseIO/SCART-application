<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\export\scartExport;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\iccam\scartICCAMfields;

/**
 * Model
 */
class Grade_question extends scartModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_grade_question';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'sortnr' => 'required|numeric',
        'type' => 'required',
        'name' => 'required',
        'label' => 'required',
    ];

    public $hasMany = [
        'options' => [
            'abuseio\scart\models\Grade_question_option',
            'key' => 'grade_question_id',
            'otherKey' => 'id',
        ],
    ];

    public function getQuestiongroupOptions($value,$formData) {

        $ret = [
            'illegal' => 'illegal',
            'not_illegal' => 'not illegal',
            'police' => 'police',
        ];
        return $ret;

    }

    public function getTypeOptions($value,$formData) {

        return [
            'select' => 'select field',
            'checkbox' => 'checkbox field',
            'radio' => 'radio field',
            'text' => 'text field',
            'section' => 'section separator',
        ];
    }

    public function getUrlTypeOptions($value,$formData) {

        return [
            'mainurl' => 'main url',
            'imageurl' => 'image/video url',
        ];
    }

    public function getIccamFieldOptions($value,$formData) {

        return scartICCAMfields::getICCAMsupportedFields();
    }

    /**    **/

    private $_iccamfield = '';
    public function isICCAMchanged($iccamfield) {
        $ret = false;
        if (!empty($this->id)) {
            // last one in db
            $ret = ($iccamfield != Grade_question::find($this->id)->iccamfield);
        }
        return $ret;
    }

    // if iccam_field then fill option values (if changed)


    public function beforeSave() {

        if ($this->id) {

            // check if ICCAM field filled and changed

            $dbiccamfield = Grade_question::find($this->id)->iccam_field;

            if ($this->iccam_field != '' && $this->iccam_field != $dbiccamfield) {

                scartLog::logLine("D-Grade_question.afterSave; id=$this->id, fill values of iccam_field=" . $this->iccam_field);

                // remove existing values -> softDelete, so traceback to orgin is possible for old answers
                Grade_question_option::where('grade_question_id',$this->id)->delete();

                // fill iccam field options
                $geticcamoptions = 'get'.$this->iccam_field.'Options';
                //$options = call_user_func(['scartICCAMfields','get'.$this->iccam_field.'Options']);
                $options = scartICCAMfields::$geticcamoptions();
                $sortnr = 1;
                foreach ($options AS $opt => $label) {
                    $option = new Grade_question_option();
                    $option->grade_question_id = $this->id;
                    $option->sortnr = $sortnr;
                    $option->value = $opt;
                    $option->label = $label;
                    $option->save();
                    $sortnr++;
                }

            } else {
                scartLog::logLine("D-Grade_question.afterSave; iccam_field not filled or changed");
            }

            $this->_iccamfield = $this->iccam_field;
        }


    }


    /** Questions get/set/fetch/save **/

    public static function getQuestionUrlType($url_type,$group=SCART_GRADE_QUESTION_GROUP_ILLEGAL) {

        // Different questions possible for SCART_GRADE_QUESTION_GROUP_ILLEGAL / SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL
        // also for SCART_GRADE_QUESTION_GROUP_POLICE ?!
        if ($url_type != SCART_URL_TYPE_MAINURL) {
            // check if questions set
            if (Grade_question::where([
                    ['questiongroup',$group],
                    ['url_type',SCART_URL_TYPE_IMAGEURL],
                ])->count() == 0) {
                // fallback -> mainurl
                $url_type = SCART_URL_TYPE_MAINURL;
            } else {
                // image/video -> imageurl
                $url_type = SCART_URL_TYPE_IMAGEURL;
            }
        }
        return $url_type;
    }

    public static function getClassifyQuestions($questiongroup,$url_type) {

        // depending on url_type
        $url_type = Grade_question::getQuestionUrlType($url_type,$questiongroup);
        return Grade_question::where('questiongroup',$questiongroup)
            ->where('url_type',$url_type)
            ->orderBy('sortnr')
            ->get();
    }


    public static function getGradeHeaders($grade_code) {

        /**
         * Create header with question labels
         * Depending on field type create one or more column heads
         *
         * For SCART_GRADE_QUESTION_GROUP_ILLEGAL collect all headers for mainurl and imageurl
         *
         */

        if (is_array($grade_code)) {
            $group = (scartExport::inFilter($grade_code,SCART_GRADE_ILLEGAL) ) ? SCART_GRADE_QUESTION_GROUP_ILLEGAL : SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL;
        } else {
            $group = ($grade_code == SCART_GRADE_ILLEGAL) ? SCART_GRADE_QUESTION_GROUP_ILLEGAL : SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL;
        }
        $grades = Grade_question::where('questiongroup', $group)
            ->orderBy('url_type','DESC')
            ->orderBy('sortnr')
            ->get();
        $gradelabels = $gradevalues = $gradetypes = [];
        // determine if more url_type questions
        $multiurltypes = (Grade_question::where('questiongroup', $group)->select('url_type')->distinct()->count() > 1);

        foreach ($grades AS $grade) {
            $gradetypes[$grade->id] = $grade->type;
            $prefixlabel = ($multiurltypes) ? "[$grade->url_type]" : '';
            if ($grade->type == 'select' || $grade->type == 'checkbox' || $grade->type == 'radio') {
                $gradevalues[$grade->id] = [];
                $opts = Grade_question_option::where('grade_question_id', $grade->id)->orderBy('sortnr')->get();
                foreach ($opts AS $opt) {
                    $gradevalues[$grade->id][$opt->value] = $opt->label;
                    if ($grade->type == 'select' || $grade->type == 'checkbox') $gradelabels['grade_'.$grade->id.'_'.$opt->value] = $prefixlabel."[$grade->label] " . $opt->label;
                }
                if ($grade->type == 'radio') $gradelabels['grade_'.$grade->id] = $prefixlabel.$grade->label;
            } elseif ($grade->type == 'text') {
                $gradelabels['grade_'.$grade->id] = $prefixlabel.$grade->label;
            }
        }

        return [
            'types' => $gradetypes,
            'values' => $gradevalues,
            'labels' => $gradelabels,
        ];
    }

    //** get & set classification (grade) answer **/

    public static function getGradeAnswer($map,$record) {

        $url_type = Grade_question::getQuestionUrlType($record->url_type,SCART_GRADE_QUESTION_GROUP_ILLEGAL);
        $gradeQuestion = Grade_question::where('questiongroup', SCART_GRADE_QUESTION_GROUP_ILLEGAL)
            ->where('name', $map)->where('url_type',$url_type)
            ->first();
        if ($gradeQuestion) {
            $answerOption = Grade_answer::where('record_type', SCART_INPUT_TYPE)
                ->where('record_id', $record->id)
                ->where('grade_question_id', $gradeQuestion->id)
                ->first();
        } else {
            $answerOption = '';
        }
        $answer =  ($answerOption) ? unserialize($answerOption->answer) : '';
        return ($answer) ?: [] ;
    }
    public static function setGradeAnswer($map,$record,$value) {

        $url_type = Grade_question::getQuestionUrlType($record->url_type,SCART_GRADE_QUESTION_GROUP_ILLEGAL);
        $gradeQuestion = Grade_question::where('questiongroup', SCART_GRADE_QUESTION_GROUP_ILLEGAL)
            ->where('name', $map)->where('url_type',$url_type)
            ->first();
        if ($gradeQuestion) {
            $answerOption = new Grade_answer();
            $answerOption->record_id = $record->id;
            $answerOption->record_type = SCART_INPUT_TYPE;
            $answerOption->grade_question_id = $gradeQuestion->id;
            $answerOption->answer = serialize($value);
            $answerOption->save();
        } else {
            $answerOption = '';
        }
        $answer =  ($answerOption) ? unserialize($answerOption->answer) : '';
        return ($answer) ?: [] ;
    }

    static function getGradeTimestamp($record) {

        // nieuwste antwoord (laatst aangepast/ingevoerd)
        $answerOption = Grade_answer::where('record_type', SCART_INPUT_TYPE)
            ->where('record_id', $record->id)
            ->orderBy('updated_at','DESC')
            ->first();
        return ($answerOption) ? strtotime($answerOption->updated_at) : time();
    }

    /** fetch classification questions **/

    public static function fetchClassifyQuestions($url_type) {

        $url_type = Grade_question::getQuestionUrlType($url_type,SCART_GRADE_QUESTION_GROUP_ILLEGAL);
        return Grade_question::where('questiongroup', SCART_GRADE_QUESTION_GROUP_ILLEGAL)
            ->where([
                ['url_type',$url_type],
                ['iccam_field','<>', ''],
            ])->get();
    }

}
