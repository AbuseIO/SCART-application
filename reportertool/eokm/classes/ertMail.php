<?php
namespace reportertool\eokm\classes;

use League\Flysystem\Exception;
use Log;
use Mail;
use Config;
use \Swift_Mailer;
use \Swift_SmtpTransport;

class ertMail {

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
            'host' => Config::get('reportertool.eokm::mail.host',''),
            'port' => Config::get('reportertool.eokm::mail.port','25'),
            'encryption' => Config::get('reportertool.eokm::mail.encryption', null),
            'username' => Config::get('reportertool.eokm::mail.username',''),
            'password' => Config::get('reportertool.eokm::mail.password',''),
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
        $smtpmail = new Swift_Mailer($transport);

        // Set the mailer
        Mail::setSwiftMailer($smtpmail);
    }

    public static function closeSmptmailer() {

        // Restore your original mailer
        Mail::setSwiftMailer(self::$_backupsmtp);
    }

    public static function sendMail($to,$mailview,$params, $bcc='' ) {

        $from = 'noreply@' . Config::get('reportertool.eokm::errors.domain','svsnet.nl');

        self::openSmptmailer();

        try {
            // Send your message
            Mail::sendTo($to, $mailview, $params, function($message) use ($bcc,$from) {
                $message->from($from, $name = null);
                // bcc for testing
                if ($bcc) $message->bcc($bcc);
            });

            //Log::info("D-sendMail succes");

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            Log::error("Error sendMail(to=$to,from=$from, mailview=$mailview): error=" . $err->getMessage() );

        }

        self::closeSmptmailer();

    }

    public static function sendMailRaw($to,$subject,$body, $from='') {

        if ($from=='') $from = 'noreply@' . Config::get('reportertool.eokm::errors.domain','svsnet.nl');

        // use own smtp mailer
        self::openSmptmailer();

        try {

            Mail::raw(['text' => strip_tags($body),'html' => $body], function($message) use ($to,$subject,$from) {
                $message->from($from, $name = null);
                $message->to($to, $name = null);
                $message->replyTo($from, $name = null);
                $message->subject($subject);
            });

            Log::info("D-sendMailRaw succes");

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            Log::error("Error sendMailRaw(to=$to,from=$from,subject=$subject): error=" . $err->getMessage() );

        }

        self::closeSmptmailer();
    }

    public static function sendNTD($to,$subject,$body,$params, $bcc='' ) {


        $from = Config::get('reportertool.eokm::scheduler.sendntd.from','from@svsnet.nl');
        $envelope_from  = Config::get('reportertool.eokm::scheduler.sendntd.envelope_from','envelop_from@svsnet.nl');
        $reply_to  = Config::get('reportertool.eokm::scheduler.sendntd.reply_to','reply_to@svsnet.nl');

        $alt_email  = Config::get('reportertool.eokm::scheduler.sendntd.alt_email','');
        if ($alt_email) {
            Log::info("D-Alternate email address (TEST MODE); use '$alt_email' for '$to' ");
            $subject = "[ALT_EMAIL active; org=$to] $subject";
            $to = $alt_email;
        }

        $message_id = '';

        self::openSmptmailer();

        try {
            // Send your message
            //Mail::send(['raw' => $body], $params, function($message) use ($to,$subject,$bcc,$from,$reply_to,$envelope_from,&$message_id,&$message_body) {
            Mail::raw($body, function($message) use ($to,$subject,$bcc,$from,$reply_to,$envelope_from,&$message_id,&$message_body) {
                $message->to($to);
                $message->subject($subject);
                // bcc if set
                if ($bcc) $message->bcc($bcc);

                $swift = $message->getSwiftMessage();
                $headers = $swift->getHeaders();

                if ($from) $message->from($from);
                if ($reply_to) $message->replyTo($reply_to);
                if ($envelope_from) {
                    $headers->addTextHeader('Envelope_from', $envelope_from);
                }

                $message_id = $message->getId();
                $message_body = $message->getBody();

            });

            //Log::info("SendNTD done (message_id=$message_id)");

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            Log::error("Error sendNTD (to=$to,from=$from): error=" . $err->getMessage() );

        }

        self::closeSmptmailer();

        return [
            'body' => $message_body,
            'id' => $message_id,
        ];
    }

    /**
     * sendAlert -> use mailview
     *
     * @param $mailview
     * @param array $mailprms
     */

    public static function sendAlert($alertlevel,$mailview,$mailprms=[]) {

        $level = Config::get('reportertool.eokm::alerts.level');
        if ($alertlevel >= $level) {

            $to = Config::get('reportertool.eokm::alerts.recipient');
            $bcc = Config::get('reportertool.eokm::alerts.bcc_recipient');

            ertLog::logLine("D-sendAlert; send report '$mailview' to: $to");
            ertMail::sendMail($to, $mailview, $mailprms, $bcc);

        } else {
            ertLog::logLine("D-Skip sending alert (alertlevel=$alertlevel < $level) of mailview=$mailview");
        }

    }




}
