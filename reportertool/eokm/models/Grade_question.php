<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class Grade_question extends ertModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_grade_question';

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
            'reportertool\eokm\models\Grade_question_option',
            'key' => 'grade_question_id',
            'otherKey' => 'id',
        ],
    ];

    public function getQuestiongroupOptions($value,$formData) {

        $ret = array(
            'illegal' => 'illegal',
            'not_illegal' => 'not illegal',
        );
        return $ret;

    }


}
