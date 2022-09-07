<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Manual extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_manual';

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
