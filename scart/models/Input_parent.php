<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Input_parent extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $belongsTo = [

        'item' => [
            'abuseio\scart\models\Input',
            'table' => 'abuseio_scart_input_parent',
            'key' => 'input_id',
            'otherKey' => 'id',
        ],


    ];
    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_parent';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
