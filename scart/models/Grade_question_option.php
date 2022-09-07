<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

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

    /**
     * @var array Validation rules
     */
    public $rules = [
        'sortnr' => 'required|numeric',
        'value' => 'required',
        'label' => 'required',
    ];
}
