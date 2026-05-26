<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;
use Flash;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Exception\ValidationException;

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


    public function beforeDelete() {

        parent::beforeDelete();

        //
        $cnt = Input::where('source_code',$this->code)->count();

        if ($cnt != 0) {
            throw new ValidationException(['source code used' => "There are $cnt input record(s) with '$this->code' source - cannot delete"]);
        }

        return ($cnt ==0);

    }

    public static function getSourcecode($source) {

        return str_replace(' ','_',$source);
    }

    public static function checkInsertCode($source) {

        $sourcecode = self::getSourcecode($source);
        $rec = Input_source::where('code','=',$sourcecode)->first();
        if (!$rec) {
            $maxsortnr = Input_source::max('sortnr');
            $rec = new Input_source();
            $rec->sortnr = ($maxsortnr) ? ($maxsortnr + 1) : 1;
            $rec->lang = 'en';
            $rec->code = $sourcecode;
            $rec->title = $source;
            $rec->description = $source;
            $rec->save();
            $new = true;
            scartLog::logLine("D-Insert new source='$source', code=$sourcecode");
        } else {
            $new = false;
        }
        return $new;
    }

}
