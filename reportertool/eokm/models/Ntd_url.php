<?php namespace ReporterTool\EOKM\Models;

use Model;

/**
 * Model
 */
class Ntd_url extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_ntd_url';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];


    public $hasOne = [
        'ntd' => [
            'reportertool\eokm\models\Ntd',
            'key' => 'id',
            'otherKey' => 'ntd_id',
        ],
    ];



}
