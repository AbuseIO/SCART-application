<?php
namespace abuseio\scart\classes\iccam\api3\models;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMcurl;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;

class ScartICCAMapi {

   function send($request = 'GET', $action='', $post='') {

        if (ICCAMAuthentication::isLoggedin()) {
            ICCAMcurl::setCredentials(ICCAMAuthentication::getToken());
            $result = ICCAMcurl::send($request,$action,$post);
        }  else {
            scartLog::logLine("E-ScartICCAMapi; cannot do '$action'; NOT logged in!?");
            $result = false;
        }
        return $result;
    }

    //\\//\\       Basic Entities      //\\//\\

    /**
     * @param string $id
     * @return bool|mixed|string
     */
    public function getCountries($id = '')
    {
        return $this->send('GET', 'rest/countries'.(!empty($id) ? '/'.$id : ''));
    }

    /**
     * @param string $id
     * @return bool|mixed|string
     */
    public function getHotlines($id = '')
    {
        return $this->send('GET', 'rest/hotlines'.(!empty($id) ? '/'.$id : ''));
    }

    /**
     * @param string $id
     * @return bool|mixed|string
     */
    public function getAnalysts($id = '')
    {
        return $this->send('GET', 'rest/analysts'.(!empty($id) ? '/'.$id : ''));
    }

    /**
     * @return bool|mixed|string
     */
    public function getAgeCategories()
    {
        return $this->send('GET', 'rest/age-categories');
    }

    /**
     * @return bool|mixed|string
     */
    public function getGenderCategories()
    {
        return $this->send('GET', 'rest/gender-categories');
    }

    /**
     * @param string $type
     * @return bool|mixed|string
     */
    public function getActionsTypes($type = '')
    {
        return $this->send('GET', 'rest/action-types'.(!empty($type) ? '/'.$type : ''));
    }

    /**
     * @return bool|mixed|string
     */
    public function getPaymentMethods()
    {
        return $this->send('GET', 'rest/payment-methods');
    }

    /**
     * @return bool|mixed|string
     */
    public function getSitesTypes()
    {
        return $this->send('GET', 'rest/site-types');
    }

    /**
     * @return bool|mixed|string
     */
    public function getCommercialities()
    {
        return $this->send('GET', 'rest/commercialities');
    }

    /**
     * @return bool|mixed|string
     */
    public function getClassifications()
    {
        return $this->send('GET', 'rest/classifications');
    }

    //\\//\\       End Entities       //\\//\\

    //\\//\\       Content      //\\//\\

    /**
     * @param int $id
     * @return bool|mixed|string
     */
    public function getContent(int $id) {
        return $this->send('GET', 'rest/Content'.(!empty($id) ? '/'.$id : ''));
    }

    /**
     * @param int $maxResults
     * @param string $datetime
     * @return bool|mixed|string
     */
    public function getUnassignedToHotline(int $maxResults = 30, string $datetime = '')
    {
        return $this->send('GET',  'rest/Content/unassigned-to-hotline?maxResults='.
            $maxResults.(!empty($datetime) ? '&countryAssignmentStartDate='.urlencode($datetime) : '') );
    }

    /**
     * @description This endpoint gives you access to all the content items that are orphaned.
     * If your hotline is one of the 5 hotlines with mandate to process orphan reports,
     * you can perform an assignment of an orphaned content item to your country and hotline.
     * @param int $maxResults
     * @param string $datetime
     * @example https://iccamapi.notion.site/response_content_orphaned-d4a8ceb03fa041958826a75b9b4c4019
     * @return bool|mixed|string
     */
    public function getOrphaned(int $maxResults = 30, $datetime = '')
    {
        return $this->send('GET',  'rest/Content/orphaned?maxResults='.
            $maxResults.(!empty($datetime) ? '&countryAssignmentStartDate='.urlencode($datetime) : '') );
    }

    /**
     * @param int $maxResults
     * @param string $datetime
     * @return bool|mixed|string
     */
    public function getOffline($maxResults = 30, $datetime = '')
    {
        return $this->send('GET',  'rest/Content/offline?maxResults='.
            $maxResults.(!empty($datetime) ? '&hotlineAssignmentStartDate='.urlencode($datetime) : '') );
    }

    /**
     * @description This endpoint gives you access to all the items that need to be assesed.
     * @param int $maxResults (Maximum number of results you would like to see. Default is 200. Max is 200)
     * @param string $datetime (Filter by start date of when content items were assigned to your hotline)
     * @example https://iccamapi.notion.site/response_content_unassesed-json-894c55c15370477fbb6af709ecb68d94
     * @return bool|mixed|string
     */
    public function getUnassessed(int $maxResults = 30, $datetime = '')
    {
        return $this->send('GET',  'rest/Content/unassessed?maxResults='.
            $maxResults.(!empty($datetime) ? '&countryAssignmentStartDate='.urlencode($datetime) : '') );
    }

    /**
     * @description This endpoint gives you access to all the content items that need to be actioned.
     * @param int $maxResults
     * @param string $datetime
     * @example https://iccamapi.notion.site/response_content_unactioned-bfe56b9b06914cd7a65b63db1e158d5c
     * @return bool|mixed|string
     */
    public function getUnactioned(int $maxResults = 30, $datetime = '') {
        return $this->send('GET',  'rest/Content/unactioned?maxResults='.
            $maxResults.(!empty($datetime) ? '&countryAssignmentStartDate='.urlencode($datetime) : '') );
    }

    /**
     * @description This endpoint gives you access to all the content items that has no hotline reference
     * @param int $maxResults
     * @param string $datetime
     * @example https://iccamapi.notion.site/response_content_unactioned-bfe56b9b06914cd7a65b63db1e158d5c
     * @return bool|mixed|string
     */
    public function getnoreference(int $maxResults = 30, $datetime = '') {
        return $this->send('GET',  'rest/Content/no-reference?maxResults='.
            $maxResults.(!empty($datetime) ? '&countryAssignmentStartDate='.urlencode($datetime) : '') );
    }

    /**
     * @Description This endpoint gives you the ability enter settings to a Content item object by id.
     *
     */
    public function postContentAction($contentId, $action) {
        return  $this->send('POST', 'rest/Content/'.$contentId.'/actions',$action);
    }

    public function putContentAssessment($contentId, $assessment) {
        return  $this->send('PUT', 'rest/Content/'.$contentId.'/assessment',$assessment);
    }

    public function putContentNewHosting($contentId, $newIpAddress, $newCountryCode) {
        return  $this->send('PUT', 'rest/Content/'.$contentId.'/url/hosting?newIpAddress='.urlencode($newIpAddress).'&newCountryCode='.$newCountryCode);
    }

    public function putContentAssignedCountry($contentId, $newCountryCode) {
        return  $this->send('PUT', 'rest/Content/'.$contentId.'/assigned-country?newCountryCode='.$newCountryCode);
    }

    public function putContentHotlineReference($contentId, $reference) {
        return  $this->send('PUT', 'rest/Content/'.$contentId.'/hotline-reference?reference='.urlencode($reference));
    }

    //\\//\\       End Content       //\\//\\

    //\\//\\       Reports      //\\//\\

    /**
     * @return bool|mixed|string
     */
    public function getReport(int $id) {
        if (empty($id)) $id = '0';
        return $this->send('GET',  'rest/Reports/'.$id );
    }

    public function postReport($reportData) {
        return  $this->send('POST', 'rest/reports',$reportData);
    }
    public function putReportCommerciality($reportId, $commerciality) {
        return  $this->send('PUT', 'rest/Reports/'.$reportId.'/source-url/commerciality?commerciality='.$commerciality);
    }
    public function putReportSiteType($reportId, $sitetype) {
        return  $this->send('PUT', 'rest/Reports/'.$reportId.'/source-url/site-type?siteType='.$sitetype);
    }
    public function putReportPaymentMethods($reportId, $PaymentMethods) {
        return  $this->send('PUT', 'rest/Reports/'.$reportId.'/source-url/payment-methods',$PaymentMethods);
    }



}
