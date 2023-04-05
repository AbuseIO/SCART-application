<?php
namespace abuseio\scart\classes\rules;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Domainrule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\URL;
use Input;
use Request;


class URLnew implements Rule
{

    public function passes($attribute, $value)
    {
        $bool = false;

        try {

            $value = (strpos( $value, 'https://' ) !== false || strpos( $value, 'http://' ) !== false) ? $value : "https://".$value;

            $bool = (filter_var($value, FILTER_VALIDATE_URL) );

        } catch (\Exception $err) {
            scartLog::logLine("W-URLnew; error when parsing: ".$err->getMessage());
        }

        if (!$bool) {
            scartLog::logLine("W-URLnew; url '$value' not valid");
        }

        return $bool;
    }

    /**
     * Validation callback method.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $params
     * @return bool
     */
    public function validate($attribute, $value, $params)
    {

        return $this->passes($attribute, $value);
    }


    /**
     * message gets the validation error message.
     * @return string
     */
    public function message()
    {
        return trans('backend::lang.form.url_not_allowed');
    }


}
