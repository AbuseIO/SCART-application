<?php namespace abuseio\scart\console;

/**
 * 2024/2/28
 *
 * Investigated:
 * - https://github.com/vgrem/phpSPO
 *   composer require vgrem/phpSPO
 * - https://github.com/microsoftgraph/msgraph-sdk-php
 *   composer require microsoft/microsoft-graph
 *
 * TESTING SCRIPT
 *
 * Note: readMailTest is testing script for imap and m365
 *
 */

use Config;

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartReadMailImap;
use abuseio\scart\classes\mail\scartImportMailbox;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

/*
use Office365\Runtime\Auth\AADTokenProvider;
use Office365\Runtime\Auth\UserCredentials;
use Office365\GraphServiceClient;
use Office365\Outlook\Message;
use Office365\Outlook\ItemBody;
use Office365\Outlook\BodyType;
use Office365\Runtime\Auth\ClientCredential;
use Office365\SharePoint\ClientContext;
use Office365\Outlook\Messages;
use Office365\Outlook\EmailAddress;
*/

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\Tasks\PageIterator;
use Microsoft\Graph\Generated\Models\Message;
use DateTimeInterface;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Messages\Item\Move\MovePostRequestBody;

class readMail365Test extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:readMail365Test';

    /**
     * @var string The console command description.
     */
    protected $description = 'Read 365 MAIL from server';

    /**
     *
     * User graph interface for reading messages from mailbox
     *
     * APP with the following API Permissions (Microsoft Azure App registration)
     * - IMAP.AccessAsUser.All
     * - Mail.ReadWrite
     * - user.Read
     * Also if more
     * - Mail.Send
     * - User.ReadBasic.All
     *
     * Needed Azure APP registration/user values:
     * - SCHEDULER_READIMPORT_M365_tenantId
     * - SCHEDULER_READIMPORT_M365_appId
     * - SCHEDULER_READIMPORT_M365_clientSecretValue
     *
     * Execute the exconsole command.
     * @return void
     */
    public function acquireToken()
    {
        $tenant = env('SCHEDULER_READIMPORT_M365_tenantId', '');
        $resource = "https://graph.microsoft.com";
        $provider = new AADTokenProvider($tenant);
        return $provider->acquireTokenForPassword($resource, env('SCHEDULER_READIMPORT_M365_appId', ''),
            new UserCredentials(env('SCHEDULER_READIMPORT_USERNAME', ''), env('SCHEDULER_READIMPORT_PASSWORD', '')));
    }


    public function handle() {

        scartLog::setEcho(true);

        scartLog::logLine("D-readMail365Test; startup");

        $tokenRequestContext = new ClientCredentialContext(
            env('SCHEDULER_READIMPORT_M365_tenantId', ''),
            env('SCHEDULER_READIMPORT_M365_appId', ''),
            env('SCHEDULER_READIMPORT_M365_clientSecret', ''),
        );
        $scopes = ['User.Read', 'Mail.ReadWrite'];

        $graphServiceClient = new GraphServiceClient($tokenRequestContext);

        /*
        try {

            scartLog::logLine("D-readMail365Test; get user");
            $user  = $graphServiceClient->users()->byUserId('Scart.Import@ecpathotline.se')->get()->wait();

            scartLog::logLine("D-readMail365Test; user=".$user->getAboutMe());

        } catch (\Exception $ex) {
            scartLog::logLine ("D-Error: " . $ex->getError()->getMessage());
        }
        */

        scartLog::logLine("D-readMail365Test; get messages");

        $maxcount = 10;

        try {

            /*
            $query = MessagesRequestBuilderGetRequestConfiguration::createQueryParameters(
                filter: 'isRead eq false',
                top: $maxcount
            );
            $requestConfig = new MessagesRequestBuilderGetRequestConfiguration($query);
            */

            $userContext = $graphServiceClient->users()->byUserId(env('SCHEDULER_READIMPORT_M365_principal', ''));

            $messages = $userContext
                ->mailFolders()
                ->byMailFolderId('inbox')
                ->messages()
                ->get()
// filter messages -> only working without mailFolder filter above -> not needed, we move all processed messages to deleteditems
//                ->get($requestConfig)
                ->wait();
            //scartLog::logDump("D-Messages=",$messages);

            if ($messages) {

                //scartLog::logLine("D-readMail365Test; process " . $messages->getOdataCount() . ' messages' );

                scartLog::logLine("D-readMail365Test; get messages (limit=$maxcount)");

                foreach ($messages->getValue() as $message) {

                    scartLog::logLine("D-sSubject: {$message->getSubject()}, Received at: {$message->getReceivedDateTime()->format(DateTimeInterface::RFC2822)}");

                    scartLog::logDump("D-body content-type=".print_r($message->getBody()->getContentType(),true).", body=",$message->getBody()->getContent());

                    break;

                    scartLog::logLine("D-readMail365Test; delete message");
                    $result = $userContext
                        ->messages()
                        ->byMessageId($message->getId())
                        ->delete()
                        ->wait();
                    break;

                    $requestBody = new MovePostRequestBody();
                    $requestBody->setDestinationId('deleteditems');
                    scartLog::logLine("D-readMail365Test; move message");
                    $result = $userContext
                        ->messages()
                        ->byMessageId($message->getId())
                        ->move()
                        ->post($requestBody)
                        ->wait();

                }


                /*
                $pageIterator = new PageIterator($messages, $graphServiceClient->getRequestAdapter());
                $counter = 0;
                $callback = function (Message $message) use (&$counter,$maxcount) {
                    scartLog::logLine("Subject: {$message->getSubject()}, Received at: {$message->getReceivedDateTime()->format(DateTimeInterface::RFC2822)}");
                    $message->setIsRead(true);
                    $counter ++;
                    return ($counter <= $maxcount);
                };
                if ($pageIterator->hasNext()) {
                    $pageIterator->iterate($callback);
                }

                */

            } else {
                scartLog::logLine("D-No message(s)");
            }

        } catch (\Exception $err) {
            if (method_exists($err,'getError')) {
                scartLog::logLine ("D-Error (getError): " . $err->getError()->getMessage());
            } else {
                scartLog::logLine ("D-Error: " . $err->getMessage());
            }

        }

        /*
        $client = new GraphServiceClient(array($this,'acquireToken'));

        scartLog::logLine("D-readMail365Test; get user details");
        $user = $client->getMe()->get()->executeQuery();
        scartLog::logDump("D-User=",$user->toJson());

        scartLog::logLine("D-readMail365Test; get messages");
        $messages = $client->getMe()
            ->getMessages()
//            ->filter('isRead eq false')
            ->top(1)
            ->get()
            ->executeQuery();
        //scartLog::logDump("D-Messages",$messages->toJson());

        if ($messages) {

            scartLog::logLine("D-readMail365Test; process " . $messages->getCount() . ' messages' );

            foreach($messages as $message) {

                $msg = (object) $message->toJson();

                scartLog::logDump("D-Got messages; ".
                    "subject=".$message->getSubject().
                    "receivedDateTime=$msg->ReceivedDateTime, ".
                    "from=".$message->getFrom()->EmailAddress['address'].", ".
                    "isRead=".$message->getIsRead()
                    );

                scartLog::logDump("D-Body",$message->getBody());

                //$message->read(true);

                $message->move('deleteditems');

            }

            $client->executeQuery();



        } else {
            scartLog::logLine("D-No message(s)");
        }
        */

        scartLog::logLine("D-readMail365Test; end");

    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments() {
        return [
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions() {
        return [
            ['delete', 'd', InputOption::VALUE_NONE, 'Delete'],
            ['subject', 's', InputOption::VALUE_OPTIONAL, 'Subject', ''],
            ['msgno', 'm', InputOption::VALUE_OPTIONAL, 'Msgno', ''],
        ];
    }


}
