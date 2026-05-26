<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Ntd_template extends scartModel {

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_ntd_template';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public function getLeaTemplatesOptions() {

        $templates = Ntd_template::all();
        $ret = [0 => '(default template from abuseconact)'];
        foreach ($templates as $template) {
            $ret[$template->id] = $template->title;
        }
        return $ret;
    }

    public function getLeaAbusecontactOptions() {

        $contacts = Abusecontact::where('lea_contact',true)->orderBy('owner')->get();
        $ret = [];
        foreach ($contacts as $contact) {
            $ret[$contact->id] = $contact->owner." ({$contact->abusecustom})";
        }
        if (empty($ret)) $ret[0] = '(no LEA contacts!?)';
        return $ret;
    }

}
