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
use \Swift_Mailer;
use \Swift_SmtpTransport;
use abuseio\scart\models\Systemconfig;

class scartMail {

    /**
     * Central class for send formated mail
     *
     * Note: Set OWN SMTP handler so we don't need to fill the System setting with our user and password (!)
     *
     * @param $to
     * @param $mailview
     * @param $params
     */

    private static $_backupsmtp = '';

    private static function _getSettings() {
        return [
            'host' => Systemconfig::get('abuseio.scart::mail.host',''),
            'port' => Systemconfig::get('abuseio.scart::mail.port','25'),
            'encryption' => Systemconfig::get('abuseio.scart::mail.encryption', null),
            'username' => Systemconfig::get('abuseio.scart::mail.username',''),
            'password' => Systemconfig::get('abuseio.scart::mail.password',''),
        ];
    }

    public static function openSmptmailer() {

        // Backup default mailer
        self::$_backupsmtp = Mail::getSwiftMailer();

        $setting = self::_getSettings();
        //Log::info("D-openSmptmailer.settings; host=" . $setting['host'] . ", port=" . $setting['port'] . ", encryption=" . $setting['encryption'] );

        // Setup own smtp mailer
        $transport = new Swift_SmtpTransport(
            $setting['host'],
            $setting['port'],
            $setting['encryption']);
        $transport->setUsername($setting['username']);
        $transport->setPassword($setting['password']);
        // can use self signed certs
        $transport->setStreamOptions([
            'ssl' => [
                'verify_peer' => false,
                'allow_self_signed' => true,
            ]
        ]);
        $smtpmail = new Swift_Mailer($transport);

        // Set the mailer
        Mail::setSwiftMailer($smtpmail);
    }

    public static function closeSmptmailer() {

        // Restore your original mailer
        Mail::setSwiftMailer(self::$_backupsmtp);
    }

    public static function sendMail($to,$mailview,$params, $bcc='' ) {

        $from = 'noreply@' . Systemconfig::get('abuseio.scart::errors.domain','svsnet.nl');

        self::openSmptmailer();

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

        self::closeSmptmailer();

    }

    public static function sendMailRaw($to,$subject,$body,$from='',$attachment='') {

        if ($from=='') $from = 'noreply@' . Systemconfig::get('abuseio.scart::errors.domain','svsnet.nl');

        $message_id = false;

        // use own smtp mailer
        self::openSmptmailer();

        try {

            Mail::raw(['text' => strip_tags($body),'html' => $body], function($message) use ($to,$subject,$from,$attachment,&$message_id) {

                $message->from($from, $name = null);
                $message->to($to, $name = null);
                $message->replyTo($from, $name = null);
                $message->subject($subject);

                // add attachment
                if ($attachment) $message->attach($attachment);

                $message_id = $message->getId();

            });

            Log::info("D-sendMailRaw succes");

        } catch(\Exception $err) {

            // WARNING; do not try to send mail at this point, else we get a loop - sendMailRaw is used in scartLog::errorMail

            // NB: \Expection is important, else not in this catch when error in Mail
            Log::error("Error sendMailRaw(to=$to,from=$from,subject=$subject): error=" . $err->getMessage() );

            $message_id = false;

        }

        self::closeSmptmailer();

        return $message_id;
    }

    public static function sendNTD($to,$subject,$body,$bcc='',$attachment='') {

        $from = Systemconfig::get('abuseio.scart::scheduler.sendntd.from','from@svsnet.nl');
        $envelope_from  = Systemconfig::get('abuseio.scart::scheduler.sendntd.envelope_from',$from);
        $reply_to  = Systemconfig::get('abuseio.scart::scheduler.sendntd.reply_to','reply_to@svsnet.nl');

        $alt_email  = Systemconfig::get('abuseio.scart::scheduler.sendntd.alt_email','');
        if ($alt_email) {
            Log::debug("D-Alternate email address (TEST MODE); use '$alt_email' for '$to' ");
            $subject = "[ALT_EMAIL active; org=$to] $subject";
            $to = $alt_email;
        }

        $message_id = $message_body = '';

        self::openSmptmailer();

        try {

            /*

            // @To-Do; conversie swift mailer to symfony mailer
            Event::listen('mailer.send', function ($mailerInstance, $view, $message) use (&$message_id) {

                $message_id = $mailerInstance->sent->getMessageId();
                scartLog::logLine("D-sendMailRaw; mailer.send.1 message_id=$message_id");
                $headers = $message->getHeaders();
                scartLog::logLine("D-sendMailRaw; mailer.send.2 headers:\n".$headers->toString());
                $message_id = $headers->get('Message-ID');
                scartLog::logLine("D-sendMailRaw; mailer.send.3 message_id=$message_id");
            });
            */

            // Send your message
            //Mail::send(['raw' => $body], $params, function($message) use ($to,$subject,$bcc,$from,$reply_to,$envelope_from,&$message_id,&$message_body) {
            Mail::raw( $body, function($message) use ($to,$subject,$bcc,$from,$reply_to,$envelope_from,&$message_id,$attachment) {

                $message->to($to);
                $message->subject($subject);
                // bcc if set
                if ($bcc) $message->bcc($bcc);

                // set special headers
                //$swift = $message->getSwiftMessage();
                //$headers = $swift->getHeaders();
                $headers = $message->getHeaders();
                if ($from) $message->from($from);
                if ($reply_to) $message->replyTo($reply_to);
                if ($envelope_from) {
                    $headers->addTextHeader('Envelope_from', $envelope_from);
                }

                // add attachment
                if ($attachment) $message->attach($attachment);

                $message_id = $message->getId();

            });

            //Log::info("SendNTD done (message_id=$message_id)");

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::errorMail("Error sendNTD (to=$to,from=$from): error=" . $err->getMessage(), $err);

        }

        self::closeSmptmailer();

        return [
            'id' => $message_id,
        ];
    }


}
