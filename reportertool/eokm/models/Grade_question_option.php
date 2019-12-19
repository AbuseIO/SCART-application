<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class Grade_question_option extends ertModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_grade_question_option';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'sortnr' => 'required|numeric',
        'value' => 'required',
        'label' => 'required',
    ];
}
