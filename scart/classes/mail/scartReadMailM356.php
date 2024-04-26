<?php  namespace abuseio\scart\classes\mail;

use abuseio\scart\models\Systemconfig;
use Db;
use Config;
use Mail;
use Log;
use abuseio\scart\classes\helpers\scartLog;
use Microsoft\Graph\Generated\Users\Item\MailFolders\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Messages\Item\MessageItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

class scartReadMailM356 {

    private static $_userContext = null;

    public static function init() {

        $tokenRequestContext = new ClientCredentialContext(
            Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.m356.tenantId',''),
            Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.m356.appId',''),
            Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.m356.clientSecret',''),
        );

        $graphServiceClient = new GraphServiceClient($tokenRequestContext);

        self::$_userContext = $graphServiceClient->users()->byUserId(Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.m356.principal',''));

    }

    public static function getInboxMessages($maxmsgs=10) {

        // request $maxmsgs message from the inbox (folder)
        $requestConfig = new MessagesRequestBuilderGetRequestConfiguration(
            queryParameters: MessagesRequestBuilderGetRequestConfiguration::createQueryParameters(
                top: $maxmsgs
            )
        );
        $m365messages = self::$_userContext
            ->mailFolders()
            ->byMailFolderId('inbox')
            ->messages()
            ->get($requestConfig)
            ->wait();

        // force plain TEXT body
        $requestConfiguration = new MessageItemRequestBuilderGetRequestConfiguration();
        $headers = [
            'Prefer' => 'outlook.body-content-type="text"',
        ];
        $requestConfiguration->headers = $headers;

        // convert to general ReadMailMessage
        $messages = [];
        foreach ($m365messages->getValue() as $msg) {
            $messages[] = new scartReadMailM356Msg(self::$_userContext
                ->messages()
                ->byMessageId($msg->getId())
                ->get($requestConfiguration)
                ->wait()
            );
        }
        return $messages;
    }


    public static function deleteMessage($msg) {

        self::$_userContext
            ->messages()
            ->byMessageId($msg->getId())
            ->delete()
            ->wait();
    }

    public static function close() {

        self::$_userContext = null;
    }

}
