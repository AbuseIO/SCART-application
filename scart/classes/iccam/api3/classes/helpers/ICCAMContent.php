<?php
namespace abuseio\scart\classes\iccam\api3\classes\helpers;


class ICCAMContent
{
    private $ICCAM;

    public function __construct(ICCAMcurl $ICCAM, $token = '')
    {
        $this->ICCAM = $ICCAM;
        $this->ICCAM->setCredentialsHeaders();
    }

    /**
     * @getUnassessed: https://iccamapi.notion.site/response_content_unassesed-json-894c55c15370477fbb6af709ecb68d94
     * @getUnactioned: https://iccamapi.notion.site/response_content_unactioned-bfe56b9b06914cd7a65b63db1e158d5c
     * @content
     */
    public function get(int $maxResults = 200, string $lastDate = '') {

        // Get reports that need to be classified
        // Max: Maximum number of results you would like to see. Default is 200. Max is 200.
        // Date: Filter by start date of when content items were assigned to your hotline.
        $content = $this->ICCAM->getUnassessed($maxResults, $lastDate);

        foreach ($content as $data){
            $this->ICCAM->getcontent($data->contentId);
        }
    }

    /**
     * @getUnactioned: https://iccamapi.notion.site/response_content_unactioned-bfe56b9b06914cd7a65b63db1e158d5c
     * @content
     */


    public function parse($reports) {

    }


}
