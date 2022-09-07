<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Ntd_url extends scartModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_ntd_url';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];


    public $hasOne = [
        'ntd' => [
            'abuseio\scart\models\Ntd',
            'key' => 'id',
            'otherKey' => 'ntd_id',
        ],
    ];

    private $_record = '';

    private function getRecord() {
        if ($this->_record == '') {
            if ($this->record_type==SCART_INPUT_TYPE) {
                $this->_record = Input::find($this->record_id);
            } else {
                $this->_record = Notification::find($this->record_id);
            }
        }
        return $this->_record;
    }

    public function getFilenumberAttribute($value) {
        $record = $this->getRecord();
        return ($record) ? $record->filenumber : '';
    }



}
