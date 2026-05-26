<?php namespace abuseio\scart\models;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\base\scartModel;
use Hash;
use Winter\Storm\Exception\ValidationException;

/**
 * Model
 */
class Input_extrafield extends scartModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_extrafield';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];


    static function getWebformField($input_id,$field_label) {

        $input = Input::find($input_id);
        $webformsubject = $input->getExtrafieldValue(SCART_INPUT_EXTRAFIELD_WEBFORM,SCART_INPUT_EXTRAFIELD_WEBFORM_SUBJECT);
        $webform = ImportWebform::where('subject',$webformsubject)->first();
        if ($webform) {
            $webformField = ImportWebformField::where('import_webform_id', $webform->id)->where('name', $field_label)->first();
        } else {
            $webformField = false;
        }
        scartLog::logLine("D-getWebformField; input_id=$input_id, field_label=$field_label, webformsubject='$webformsubject', webformfield found=".(($webformField)?'yes':'no'));
        return $webformField;
    }

    public static function isExtraPassword($record,$column) {

        //scartLog::logDump("D-isExtraPassword: column.label=",$column->label);

        //$text = $record->{$column->columnName};
        $isExtraPassword = false;
        if ($record->type == SCART_INPUT_EXTRAFIELD_WEBFORM && $column->label=='value') {
            $webformField = self::getWebformField($record->input_id,$record->label);
            if ($webformField && $webformField->password_protected) {
                $isExtraPassword = true;
            }
        }
       return $isExtraPassword;
    }

    public static function getDecryptedExtraField($input_id,$field_label,$password) {

        $value = '';
        $webformField = self::getWebformField($input_id,$field_label);

        if ($webformField && Hash::check($password,$webformField->password)) {
            $extraField = Input_extrafield::where('input_id',$input_id)
                ->where('type',SCART_INPUT_EXTRAFIELD_WEBFORM)
                ->where('label',$field_label)
                ->first();
            $value = ($extraField) ? $extraField->value : '';
            scartLog::logLine("D-getDecryptedExtraField; password okay");
        } else {
            scartLog::logLine("D-getDecryptedExtraField; password '$password' wrong");
        }
        return $value;
    }


    public function beforeDelete() {

        $valid = ($this->type == SCART_INPUT_EXTRAFIELD_WEBFORM);
        if ($valid) {
            // check special webform fields
            $valid = $this->label != SCART_INPUT_EXTRAFIELD_WEBFORM_SUBJECT;
        }
        if (!$valid) {
            throw new ValidationException(['extrafield' => 'This extra field cannot be deleted']);
        }
        scartLog::logLine("D-Input_extrafield; '{$this->label}' removed");
        $input = Input::find($this->input_id);
        if ($input) {
            $input->logText("Removed extra field {$this->label}");
        }
    }


}
