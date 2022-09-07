<?php namespace abuseio\scart\models;

/**
 * WHOIS CACHE
 *
 * Simple database cache with no softdelete
 *
 */

use abuseio\scart\classes\base\scartModel;

class Whois_cache extends scartModel {
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_whois_cache';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'target' => 'required',
        'target_type' => 'required',
        'max_age' => 'required',
    ];

    public $hasOne = [
        'abusecontact' => [
            'abuseio\scart\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'abusecontact_id'
        ],
    ];




}
