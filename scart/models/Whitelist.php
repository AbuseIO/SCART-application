<?php namespace abuseio\scart\Models;

use Model;

/**
 * Model
 */
class Whitelist extends Model
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_whitelist';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public static function emailIsWhitelisted($email) {

        $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
        preg_match_all($pattern, $email, $matches, PREG_PATTERN_ORDER);
        $email = reset($matches);
        if (is_array($email)) {
            $email = reset($email);
        }
        return Whitelist::where('email', '=', $email)->count() > 0;
    }
}
