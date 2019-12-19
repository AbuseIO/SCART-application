<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class Log extends ertModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_log';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $hasOne = [
        'user' => [
            'Backend\Models\User',
            'table' => 'backend_users',
            'key' => 'id',
            'otherKey' => 'user_id',
        ],
    ];




}
