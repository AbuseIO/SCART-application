<?php namespace abuseio\scart\models;

use Model;

/**
 * Model
 */
class Iccam_hotline extends Model
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_iccam_hotline';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
