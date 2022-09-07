<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;
use System\Models\File;

/**
 * Model
 */
class Input_import extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_import';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'workuser_id' => 'required',
        'import_file' => 'required',
    ];

    public $attachOne = [
        'import_file' => ['System\Models\File','public' => false],
    ];

    public function getWorkuserIdOptions($value,$formData) {
        return $this->getGeneralWorkuserIdOptions($value,$formData);
    }


}
