<?php
namespace abuseio\scart\classes\mail;

use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Mail\Transport\MailgunTransport;
use League\Flysystem\Exception;
use Log;
use Lang;
use Mail;
use Event;
use Config;
use abuseio\scart\models\Systemconfig;

class scartMail {

    /**
     * get message id depending on the supported methode
     *
     * Laravel <= 8.x; swiftMessage
     * Larvael >= 9.x; symfonyMessage
     *
     * @param $message
     * @return mixed|string
     */

    static function getMessageID($message) {

        $message_id = '';
        if (method_exists($message,'getSymfonyMessage')) {
            $symfonymessage = $message->getSymfonyMessage();
            if (method_exists($symfonymessage,'generateMessageId')) {
                $message_id = $symfonymessage->generateMessageId();
                scartLog::logLine("D-symfonymessage->generateMessageId=" . $message_id);
            } else {
                scartLog::logLine("D-No symfonymessage->generateMessageId supported");
            }
        } elseif (method_exists($message,'getSwiftMessage')) {
            $swiftmessage = $message->getSwiftMessage();
            if (method_exists($swiftmessage,'getId')) {
                $message_id = $swiftmessage->getId();
                scartLog::logLine("D-swiftmessage->getId=" . $message_id);
            } else {
                scartLog::logLine("D-No swiftmessage->getId supported");
            }
        } else {
            scartLog::logLine("D-No transport methode");
        }
        return $message_id;
    }

    /**
     * Central class for send formated mail
     *
     * @param $to
     * @param $mailview
     * @param $params
     */

    public static function sendMail($to,$mailview,$params, $bcc='' ) {

        $from = 'noreply@' . Systemconfig::get('abuseio.scart::errors.domain','local.domain');

        try {

            // get language
            $lang = Lang::getLocale();
            // language version
            $mailview = str_replace('::mail.',"::mail.$lang.",$mailview);

            // Send your message
            Mail::sendTo($to, $mailview, $params, function($message) use ($bcc,$from) {
                $message->from($from, $name = null);
                // bcc for testing
                if ($bcc) $message->bcc($bcc);
            });

            //Log::info("D-sendMail succes");

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::errorMail("Error sendMail(to=$to,from=$from, mailview=$mailview): error=" . $err->getMessage() , $err);

        }

    }

    public static function sendMailRaw($to,$subject,$body,$from='',$attachment='') {

        if ($from=='') $from = 'noreply@' . Systemconfig::get('abuseio.scart::errors.domain','local.domain');

        $message_id = false;

        try {

            Mail::raw(['text' => strip_tags($body),'html' => $body], function($message) use ($to,$subject,$from,$attachment,&$message_id) {

                $message->from($from, $name = null);
                $message->to($to, $name = null);
                $message->replyTo($from, $name = null);
                $message->subject($subject);

                // add attachment
                if ($attachment) $message->attach($attachment);

                // get message-iD (if supported)
                $message_id = self::getMessageID($message);

            });

            scartLog::logLine("D-sendMailRaw done");

        } catch(\Exception $err) {

            // WARNING; do not try to send mail at this point, else we get a loop - sendMailRaw is used in scartLog::errorMail

            // NB: \Expection is important, else not in this catch when error in Mail
            Log::error("Error sendMailRaw(to=$to,from=$from,subject=$subject): error=" . $err->getMessage() . " on line ". $err->getLine());

            $message_id = false;

        }

        return $message_id;
    }

    public static function sendNTD($to,$subject,$body,$bcc='',$attachment='') {

        $from = Systemconfig::get('abuseio.scart::scheduler.sendntd.from','from@local.domain');
        $envelope_from  = Systemconfig::get('abuseio.scart::scheduler.sendntd.envelope_from',$from);
        $reply_to  = Systemconfig::get('abuseio.scart::scheduler.sendntd.reply_to','reply_to@local.domain');

        $alt_email  = Systemconfig::get('abuseio.scart::scheduler.sendntd.alt_email','');
        if ($alt_email) {
            Log::debug("D-Alternate email address (TEST MODE); use '$alt_email' for '$to' ");
            $subject = "[ALT_EMAIL active; org=$to] $subject";
            $to = $alt_email;
        }

        $message = '';

        try {

            // Send message
            Mail::raw( $body, function($message) use ($to,$subject,$bcc,$from,$reply_to,$envelope_from,&$message_id,$attachment) {

                $message->to($to);
                $message->subject($subject);
                // bcc if set
                if ($bcc) $message->bcc($bcc);

                // set special headers
                $headers = $message->getHeaders();
                if ($from) $message->from($from);
                if ($reply_to) $message->replyTo($reply_to);
                if ($envelope_from) {
                    $headers->addTextHeader('Envelope_from', $envelope_from);
                }

                // add attachment
                if ($attachment) $message->attach($attachment);

                // get message-iD (if supported)
                $message_id = self::getMessageID($message);

            });

            scartLog::logLine("D-SendNTD done");

            // Note: message_id can also be empty
            $message = [
                'id' => $message_id,
            ];

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::errorMail("Error sendNTD (to=$to,from=$from): error=" . $err->getMessage(), $err);

        }

        return $message;
    }


}
