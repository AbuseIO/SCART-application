<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\helpers\scartLog;
use Backend\Classes\Controller;
use BackendMenu;
use Redirect;

class ImportWebform extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'webforms');
    }


    public function onCopyForms() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $form = \abuseio\scart\Models\ImportWebform::find($check);
            if ($form) {

                $copyform = $this->copyRecord($form);
                $copyform->name = $form->name . " - COPIED";
                $copyform->subject = $form->subject . " - COPIED";
                $copyform->save();
                scartLog::logLine("D-Form {$form->label} copied to ".$copyform->label);

                foreach ($form->fields as $fields) {
                    $copyField = $this->copyRecord($fields);
                    $copyField->import_webform_id = $copyform->id;
                    $copyField->save();
                }
                scartLog::logLine("D-".count($form->fields)." field copied ");

            }

        }

        return Redirect::refresh();
    }

    function copyRecord($fromRecord) {

        $class = get_class($fromRecord);
        $newRecord = new $class();
        foreach ($fromRecord->getAttributes() as $attribute => $value) {
            if (!in_array($attribute,[$fromRecord->getKeyName(),'created_at','updated_at','deleted_at'])) {
                //scartLog::logLine("D-CopyRecord; class=$class, attribute=$attribute");
                $newRecord->$attribute = $value;
            }
        }
        return $newRecord;
    }


}
