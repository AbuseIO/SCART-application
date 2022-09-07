<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Input_lock extends scartModel
{
    use \October\Rain\Database\Traits\Validation;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_lock';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];





}
