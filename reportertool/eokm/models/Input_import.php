<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;
use reportertool\eokm\classes\ertLog;
use System\Models\File;

/**
 * Model
 */
class Input_import extends ertModel
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_input_import';

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
