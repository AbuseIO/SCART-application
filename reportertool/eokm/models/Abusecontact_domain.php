<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class Abusecontact_domain extends ertModel
{
    use \October\Rain\Database\Traits\Validation;
    
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];
    protected $jsonable = ['domains'];

    public $hasOne = [
        'host_abusecontact' => [
            'reportertool\eokm\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'host_abusecontact_id'
        ],
        'registrar_abusecontact' => [
            'reportertool\eokm\models\Abusecontact',
            'key' => 'id',
            'otherKey' => 'registrar_abusecontact_id'
        ],
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_abusecontact_domain';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'domains' => 'required|unique:reportertool_eokm_abusecontact_domain,domains',
    ];






}
