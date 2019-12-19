<?php namespace ReporterTool\EOKM\Models;

use reportertool\eokm\classes\ertModel;

/**
 * Model
 */
class Notification_selected extends ertModel {

    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'reportertool_eokm_notification_selected';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
