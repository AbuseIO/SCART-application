<?php namespace abuseio\scart\models;

use Db;
use Config;
use BackendAuth;
use abuseio\scart\classes\base\scartModel;

/**
 * Model
 */
class Token extends scartModel
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_tokens';

    public $rules = [];

}
