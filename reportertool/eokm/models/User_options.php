<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class User_options extends ertModel {
    use \October\Rain\Database\Traits\Validation;
    
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_user_options';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
