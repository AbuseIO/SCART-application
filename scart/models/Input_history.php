<?php namespace abuseio\scart\Models;

use abuseio\scart\classes\base\scartModel;


/**
 * Model
 */
class Input_history extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_history';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $hasOne = [
        'user' => [
            'Backend\Models\User',
            'table' => 'backend_users',
            'key' => 'id',
            'otherKey' => 'workuser_id',
        ],
    ];

    // show OLD/NEW; when HOSTERD then get abusecontact owner
    public function showChangedValue($recordvalue) {

        $value = '';
        if ($this->tag == SCART_INPUT_HISTORY_HOSTER) {
            $ab = Abusecontact::find($recordvalue);
            if ($ab) {
                $value =  $ab->owner;
//            } else {
//                $value = '(not set)';
            }
        } else {
            $value =  $recordvalue;
        }
        return $value;
    }

}
