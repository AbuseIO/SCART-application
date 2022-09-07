<?php namespace abuseio\scart\models;

use Model;

/**
 * Model
 */
class Audittrail extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_audittrail';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
