<?php  namespace abuseio\scart\classes\mail;

use Db;
use Config;
use Mail;
use Log;
use abuseio\scart\classes\helpers\scartLog;

class scartIMAPmail {

    static private $_host = null;
    static private $_port = null;
    static private $_sslflag = null;
    static private $_username = null;
    static private $_password = null;
    static private $_client = null;

    static private $_messagcount = 0;

    public static function parseRfc822($string) {
        $address = '';
        try {
            $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
            preg_match_all($pattern, $string, $matches, PREG_PATTERN_ORDER);
            scartLog::logLine("I-scartIMAPmail: matches=".print_r($matches,true));
            if (count($matches[0]) > 0) {
                // pick only first element; when format is "email" <email> we got 2x address
                $address = (isset($matches[0][0])?$matches[0][0]:'');
            }
        } catch (\Exception $err) {
            scartLog::logLine("E-scartIMAPmail: error parseRfc822 on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
        }
        return $address;
    }

    public static function setConfig($config) {
        self::$_host = (isset($config['host'])) ? $config['host'] : '';
        self::$_port = (isset($config['port'])) ? $config['port'] : '143';
        self::$_sslflag = (isset($config['sslflag'])) ? $config['sslflag'] : '/novalidate-cert';
        self::$_username = (isset($config['username'])) ? $config['username'] : '';
        self::$_password = (isset($config['password'])) ? $config['password'] : '';
    }

    /** IMAP CLIENT **/

    public static function imapInit() {

        // Connect to mailbox

        if (self::$_host) {

            $mailbox = '{'.self::$_host.':'.self::$_port.self::$_sslflag.'}INBOX';

            try {

                //scartLog::logLine("D-scartIMAPmail: imap_open($mailbox, $username)");
                SELF::$_client = imap_open($mailbox, self::$_username, self::$_password);

            } catch (\Exception $err) {
                scartLog::logLine("E-scartIMAPmail: exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        } else {
            scartLog::logLine("E-scartIMAPmail: importExport config NOT set!?");
        }

        return (SELF::$_client != null);
    }

    public static function imapGetInboxMessages($maxmsgs=10) {

        $headers = '';

        if (SELF::$_client==null) SELF::imapInit();

        if (SELF::$_client!=null) {
            $imap = imap_check(SELF::$_client);
            if ($imap->Nmsgs >= 1) {
                SELF::$_messagcount = $imap->Nmsgs;
                $readmsgs = ($maxmsgs != 0) ? (($maxmsgs > $imap->Nmsgs) ? $imap->Nmsgs : $maxmsgs) : $imap->Nmsgs;
                $headers = imap_fetch_overview(SELF::$_client, "1:$readmsgs", 0);
            }
        } else {
            scartLog::logLine("E-scartIMAPmail: No IMAP clientcontext ");
        }

        return $headers;
    }

    public static function imapLastMessageCount() {
        return SELF::$_messagcount;
    }

    public static function imapGetMessageBody($msg_numer) {

        if (SELF::$_client==null) SELF::imapInit();
        if (SELF::$_client!=null) {
            // body text part
            $body = imap_fetchbody (SELF::$_client, $msg_numer, 1);
            // 2020/7/16/Gs: skip
            //if ($body) $body = quoted_printable_decode($body);
        } else {
            $body = '';
        }
        return $body;
    }

    public static function imapDeleteMessage($msg_numer) {
        // always reconnect (to overcome timeout)
        //if (SELF::$_client==null) SELF::imapInit();
        SELF::imapInit();
        if (SELF::$_client!=null) {
            imap_delete(SELF::$_client,$msg_numer);
        }
    }

    public static function closeExpunge() {
        // always reconnect (to overcome timeout)
        //if (SELF::$_client==null) SELF::imapInit();
        SELF::imapInit();
        if (SELF::$_client!=null) {
            imap_expunge(SELF::$_client);
            imap_close(SELF::$_client);
            SELF::$_client = null;
        }
    }



}
