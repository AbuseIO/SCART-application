<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\models\Input;

/**
 * Model
 */
class ImportExport_job extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_importexport_job';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];


    public function getFilenumberAttribute() {

        $jobdata = unserialize($this->data);
        $input = Input::find($jobdata['record_id']);
        return ($input) ? $input->filenumber : '?';
    }

    public function getReferenceAttribute() {

        $jobdata = unserialize($this->data);
        $input = Input::find($jobdata['record_id']);
        return ($input) ? $input->reference : '?';
    }



}
