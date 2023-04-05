<?php namespace abuseio\scart\Models;

use Model;

/**
 * Model
 */
class Iccam_api_field extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    
    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_iccam_api_field';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
