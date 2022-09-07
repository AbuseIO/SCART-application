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
        $value = (strpos( $value, 'https://' ) !== false || strpos( $value, 'http://' ) !== false) ? $value : "http://".$value;

        if (filter_var($value, FILTER_VALIDATE_URL) ) {
            $headers = @get_headers($value);

            if(is_array($headers) && isset($headers[0])) {
                $bool = (strpos($headers[0], 'HTTP') !== false);
            }
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
