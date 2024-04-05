<?php  namespace abuseio\scart\classes\mail;

use abuseio\scart\models\Systemconfig;
use Db;
use Config;
use Mail;
use Log;
use abuseio\scart\classes\helpers\scartLog;

class scartReadMailImap {

    static private $_host = null;
    static private $_port = null;
    static private $_sslflag = null;
    static private $_username = null;
    static private $_password = null;
    static private $_client = null;
    static private $_messagcount = 0;


    public static function init() {

        self::$_host = Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.imap.host','');
        self::$_port = Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.imap.port','');
        self::$_sslflag = Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.imap.sslflag','/novalidate-cert');
        self::$_username = Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.imap.username','');
        self::$_password = Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.imap.password','');
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
                scartLog::logLine("E-scartReadMailImap: exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        } else {
            scartLog::logLine("E-scartReadMailImap: importExport config NOT set!?");
        }

        return (SELF::$_client != null);
    }

    public static function imapGetInboxMessages($maxmsgs=10) {

        $headers = $messages = [];

        if (SELF::$_client==null) SELF::imapInit();

        if (SELF::$_client!=null) {
            $imap = imap_check(SELF::$_client);
            if ($imap->Nmsgs >= 1) {
                SELF::$_messagcount = $imap->Nmsgs;
                $readmsgs = ($maxmsgs != 0) ? (($maxmsgs > $imap->Nmsgs) ? $imap->Nmsgs : $maxmsgs) : $imap->Nmsgs;
                $headers = imap_fetch_overview(SELF::$_client, "1:$readmsgs", 0);
            }
        } else {
            scartLog::logLine("E-scartReadMailImap: No IMAP clientcontext ");
        }

        // return array with Imap messages
        foreach ($headers as $header) {
            $messages[] = new scartReadMailImapMsg($header);
        }

        return $messages;
    }

    public static function imapLastMessageCount() {
        return SELF::$_messagcount;
    }


    public static function imapGetMessageBody($msg_numer) {

        if (SELF::$_client==null) SELF::imapInit();
        if (SELF::$_client!=null) {
            // body text part -> always 1
            $body = imap_fetchbody (SELF::$_client, $msg_numer, 1);
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
        SELF::imapInit();
        if (SELF::$_client!=null) {
            imap_expunge(SELF::$_client);
            imap_close(SELF::$_client);
            SELF::$_client = null;
        }
    }



}
