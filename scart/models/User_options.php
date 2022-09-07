<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class User_options extends scartModel {
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_user_options';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
