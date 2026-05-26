<?php namespace abuseio\scart\Models;

use abuseio\scart\classes\helpers\scartLog;
use Model;
use Hash;
use abuseio\scart\models\Input_status;

/**
 * Model
 */
class ImportWebformField extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_import_webform_field';


    /**
     * @var array Attribute names to encode and decode using JSON.
     */
    public $jsonable = [];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'name' => 'required',
        'type' => 'required',
        'import_fieldname' => 'required',
    ];

    public $customMessages = [
        'import_fieldname.unique'    => 'This import-to-field code cannot be used twice (except as extra attribute)',
    ];

    public function beforeSave() {

        scartLog::logLine("D-ImportWebformField.beforeSave");

        if (!empty($this->name)) {
            $this->name = str_replace(' ','_',$this->name);
        }
        if ($this->password_protected && !empty($this->password)) {
            $hash = Hash::make($this->password);
            scartLog::logLine("D-ImportWebformField.password={$this->password}, hash=$hash");
            $this->password = $hash;
        }
        if (!$this->password_protected) {
            $this->password = '';
        }
    }

    public function beforeValidate() {

        if (in_array($this->import_fieldname,[SCART_IMPORT_WEBFORM_URL,SCART_IMPORT_WEBFORM_NTD_NOTE,SCART_IMPORT_WEBFORM_REFERER,SCART_IMPORT_WEBFORM_NOTE])) {
            $this->rules['import_fieldname'] = 'required|unique:abuseio_scart_import_webform_field,import_fieldname,NULL,id,import_webform_id,'.$this->import_webform_id;
        }

    }

    public function getImportFieldnameOptions () {

        return [
            SCART_IMPORT_WEBFORM_URL => SCART_IMPORT_WEBFORM_URL,
            SCART_IMPORT_WEBFORM_REFERER => SCART_IMPORT_WEBFORM_REFERER,
            SCART_IMPORT_WEBFORM_NOTE => SCART_IMPORT_WEBFORM_NOTE,
            SCART_IMPORT_WEBFORM_NTD_NOTE => SCART_IMPORT_WEBFORM_NTD_NOTE,
            SCART_IMPORT_WEBFORM_IMAGE => SCART_IMPORT_WEBFORM_IMAGE,
            SCART_IMPORT_WEBFORM_ATTRIBUTE => SCART_IMPORT_WEBFORM_ATTRIBUTE,
        ];
    }


}
