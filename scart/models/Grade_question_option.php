<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;

/**
 * Model
 */
class Grade_question_option extends scartModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_grade_question_option';

    public $hasOne = [
        'gradeQuestion' => [
            'abuseio\scart\models\Grade_question',
            'key' => 'id',
            'otherKey' => 'grade_question_id'
        ],
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'sortnr' => 'required|numeric',
        'value' => 'required',
        'label' => 'required',
    ];

    public function beforeSave() {

        scartLog::logLine("D-Grade_question_option.beforeSave");

        if (!empty($this->value)) {
            $this->value = str_replace(' ','_',$this->value);
        }
    }

    public function beforeValidate() {

//        if (in_array($this->gradeQuestion->type,['select','checkbox','radio'])) {
//            $this->rules['value'] = 'required|unique:abuseio_scart_grade_question_option,value,NULL,id,grade_question_id,'.$this->grade_question_id;
//        }
    }


}
