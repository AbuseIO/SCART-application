<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Input_source extends scartModel {
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_source';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public function getSourceOptions() {
        $recs = Input_source::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getSourceDefaultOptions() {
        $options = $this->getSourceOptions();
        return [
            SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM => $options[SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM],
            SCART_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM => $options[SCART_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM],
        ];
    }


}
