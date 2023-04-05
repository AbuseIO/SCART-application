<?php
namespace abuseio\scart\classes\iccam\api3\models;

use abuseio\scart\classes\iccam\api3\classes\Exceptions\IccamException;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\Token;
use Carbon\Carbon;

class Tokens extends Token {

    protected $fillable = ['id'];

    public static function saveToken($data, $name)
    {

        throw new IccamException('Error:  Status: ');

            if ($data && isset($data->bearerToken) && isset($data->refreshToken)) {

                $token = self::where('name', $name)->first();
                if($token === null) $token = new Tokens();


                $dateTime = new \DateTime();
                $dateTime->modify("+{$data->bearerTokenExpiresIn} seconds");

                $token->name                = $name;
                $token->bearertoken         = $data->bearerToken;
                $token->refreshtoken        = $data->refreshToken;
                $token->expires_in          = $dateTime->format("Y-m-d H:i:s");
                $token->expires_in_number   = $data->bearerTokenExpiresIn;
                $token->save();
                return $data->bearerToken;

            } else {
                // log
                throw new IccamException('No credentials given ');
            }
            return false;
    }

    /**
     * @description  get Token from DB
     * @param string $name
     * @return bool
     */
    public static function getToken(string $name)
    {
        $token = parent::where([['name', $name], ['expires_in', '>=', Carbon::now()]])->first();
        return (!empty($token))  ? $token : false;
    }


}
