<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Abusecontact_domain extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];
    protected $jsonable = ['domains'];

    public $hasOne = [
        'host_abusecontact' => [
            'abuseio\scart\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'host_abusecontact_id'
        ],
        'registrar_abusecontact' => [
            'abuseio\scart\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'registrar_abusecontact_id'
        ],
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_abusecontact_domain';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'domains' => 'required|unique:abuseio_scart_abusecontact_domain,domains',
    ];






}
