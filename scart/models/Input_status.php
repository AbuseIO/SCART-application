<?php namespace abuseio\scart\models;

use BackendAuth;
use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Input_status extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_status';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public function getGradeCodes(){
        $grade_codes = Grade_status::orderBy('sortnr')->select('code','title','description')->get();
        $options = array();

        foreach ($grade_codes as $code) {
            $options[$code->code] = $code->title . ' - ' . $code->description;
        }

        return $options;
    }

    public function getStatusOptions() {
        $recs = Input_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $options = array();
        foreach ($recs AS $rec) {
            $options[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $options;
    }

    public function getStatusDefaultOptions() {
        $options = $this->getStatusOptions();
        $recs = [
            SCART_STATUS_OPEN => $options[SCART_STATUS_OPEN],                 // @TO-DO; lang translation
            SCART_STATUS_WORKING => $options[SCART_STATUS_WORKING],                 // @TO-DO; lang translation
            SCART_STATUS_SCHEDULER_SCRAPE => $options[SCART_STATUS_SCHEDULER_SCRAPE],          // @TO-DO; lang translation
            SCART_STATUS_CANNOT_SCRAPE => $options[SCART_STATUS_CANNOT_SCRAPE],          // @TO-DO; lang translation
        ];
        return $recs;
    }



}
