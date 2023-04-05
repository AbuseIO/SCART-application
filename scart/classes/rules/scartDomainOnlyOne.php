<?php


namespace abuseio\scart\classes\rules;

use abuseio\scart\models\Domainrule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\URL;
use Input;
use Request;


class scartDomainOnlyOne implements Rule
{

    public function passes($attribute, $value)
    {
        // init variable
        $return  = true;
        if (Request::is(['*/grade/update/*', '*/create'])) {
            if ($inputpost = Input::post('Domainrule', input::all())) {
                $return = !Domainrule::where('domain', $inputpost['domain'])
                    ->whereIn('type_code', [SCART_RULE_TYPE_HOST_WHOIS,SCART_RULE_TYPE_REGISTRAR_WHOIS, SCART_RULE_TYPE_PROXY_SERVICE])
                    ->exists();
            }
        }

     return  $return;
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
        return trans('backend::lang.form.rule_not_allowed');
    }


}
