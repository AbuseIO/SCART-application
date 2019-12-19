<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class Manual extends ertModel
{
    use \October\Rain\Database\Traits\Validation;
    
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_manual';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public function getLangOptions($value,$formData) {

        $ret = [
            'NL' => 'NL',
            'EN' => 'EN',
        ];
        return $ret;
    }

}
