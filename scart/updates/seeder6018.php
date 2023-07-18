<?php namespace abuseio\scart\Updates;

use Db;
use Seeder;

class Seeder6018 extends Seeder
{
    public function run()
    {
        // 2023/3/17; table holds ICCAM fields and SCART references (name & code)

        Db::table('abuseio_scart_iccam_api_field')->truncate();

        Db::table('abuseio_scart_iccam_api_field')->insert([
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "2",'iccam_name' => "Not Determined",'scart_field' => "SiteTypeID",'scart_code' => "notdetermined",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "2",'iccam_name' => "Website",'scart_field' => "SiteTypeID",'scart_code' => "website",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "3",'iccam_name' => "File host",'scart_field' => "SiteTypeID",'scart_code' => "filehost",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "4",'iccam_name' => "Image store",'scart_field' => "SiteTypeID",'scart_code' => "imagestore",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "5",'iccam_name' => "Image board",'scart_field' => "SiteTypeID",'scart_code' => "imageboard",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "6",'iccam_name' => "Forum",'scart_field' => "SiteTypeID",'scart_code' => "forum",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "7",'iccam_name' => "Banner site",'scart_field' => "SiteTypeID",'scart_code' => "bannersite",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "8",'iccam_name' => "Link site",'scart_field' => "SiteTypeID",'scart_code' => "linksite",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "9",'iccam_name' => "Social Networking",'scart_field' => "SiteTypeID",'scart_code' => "socialsite",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "10",'iccam_name' => "Redirector",'scart_field' => "SiteTypeID",'scart_code' => "redirector",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "11",'iccam_name' => "Web archive",'scart_field' => "SiteTypeID",'scart_code' => "webarchived",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "18",'iccam_name' => "Search provider",'scart_field' => "SiteTypeID",'scart_code' => "searchprovider",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "20",'iccam_name' => "Image host",'scart_field' => "SiteTypeID",'scart_code' => "imagehost",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "22",'iccam_name' => "Blog",'scart_field' => "SiteTypeID",'scart_code' => "blog",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "24",'iccam_name' => "Not Applicable",'scart_field' => "SiteTypeID",'scart_code' => "notapplicable",],
            ['iccam_field' => "sourceUrlCommerciality",'iccam_id' => "1",'iccam_name' => "Not Determined",'scart_field' => "CommercialityID",'scart_code' => "1",],
            ['iccam_field' => "sourceUrlCommerciality",'iccam_id' => "2",'iccam_name' => "Commercial",'scart_field' => "CommercialityID",'scart_code' => "2",],
            ['iccam_field' => "sourceUrlCommerciality",'iccam_id' => "3",'iccam_name' => "Non-Commercial",'scart_field' => "CommercialityID",'scart_code' => "3",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "1",'iccam_name' => "Not Determined",'scart_field' => "PaymentMethodID",'scart_code' => "1",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "2",'iccam_name' => "AMEX",'scart_field' => "PaymentMethodID",'scart_code' => "2",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "3",'iccam_name' => "Diners",'scart_field' => "PaymentMethodID",'scart_code' => "3",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "4",'iccam_name' => "Mastercard",'scart_field' => "PaymentMethodID",'scart_code' => "4",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "5",'iccam_name' => "Paypal",'scart_field' => "PaymentMethodID",'scart_code' => "5",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "6",'iccam_name' => "Visa",'scart_field' => "PaymentMethodID",'scart_code' => "6",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "7",'iccam_name' => "Western Union",'scart_field' => "PaymentMethodID",'scart_code' => "7",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "8",'iccam_name' => "Other",'scart_field' => "PaymentMethodID",'scart_code' => "8",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "9",'iccam_name' => "None",'scart_field' => "PaymentMethodID",'scart_code' => "9",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "10",'iccam_name' => "SMS",'scart_field' => "PaymentMethodID",'scart_code' => "10",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "11",'iccam_name' => "EMAIL",'scart_field' => "PaymentMethodID",'scart_code' => "11",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "12",'iccam_name' => "Liberty Reserve",'scart_field' => "PaymentMethodID",'scart_code' => "12",],
            ['iccam_field' => "sourceUrlPaymentMethods",'iccam_id' => "13",'iccam_name' => "Bitcoin",'scart_field' => "PaymentMethodID",'scart_code' => "13",],
            ['iccam_field' => "action",'iccam_id' => "1",'iccam_name' => "ReportedToLEA",'scart_field' => "actionID",'scart_code' => "1",],
            ['iccam_field' => "action",'iccam_id' => "2",'iccam_name' => "ReportedToISP",'scart_field' => "actionID",'scart_code' => "2",],
            ['iccam_field' => "action",'iccam_id' => "3",'iccam_name' => "Removed",'scart_field' => "actionID",'scart_code' => "3",],
            ['iccam_field' => "action",'iccam_id' => "4",'iccam_name' => "Unavailable",'scart_field' => "actionID",'scart_code' => "4",],
            ['iccam_field' => "action",'iccam_id' => "0",'iccam_name' => "Moved",'scart_field' => "actionID",'scart_code' => "5",],
            ['iccam_field' => "action",'iccam_id' => "6",'iccam_name' => "NotIllegal",'scart_field' => "actionID",'scart_code' => "7",],
            ['iccam_field' => "classification",'iccam_id' => "1",'iccam_name' => "Baseline CSAM",'scart_field' => "ClassificationID",'scart_code' => "BA",],
            ['iccam_field' => "classification",'iccam_id' => "2",'iccam_name' => "National CSAM",'scart_field' => "ClassificationID",'scart_code' => "NA",],
            ['iccam_field' => "classification",'iccam_id' => "4",'iccam_name' => "Ignore",'scart_field' => "ClassificationID",'scart_code' => "IG",],
            ['iccam_field' => "ageCategorization",'iccam_id' => "1",'iccam_name' => "Not categorised",'scart_field' => "AgeGroupID",'scart_code' => "ND",],
            ['iccam_field' => "ageCategorization",'iccam_id' => "2",'iccam_name' => "Infant",'scart_field' => "AgeGroupID",'scart_code' => "IN",],
            ['iccam_field' => "ageCategorization",'iccam_id' => "3",'iccam_name' => "Pre-pubescent",'scart_field' => "AgeGroupID",'scart_code' => "PP",],
            ['iccam_field' => "ageCategorization",'iccam_id' => "4",'iccam_name' => "Pubescent",'scart_field' => "AgeGroupID",'scart_code' => "PU",],
            ['iccam_field' => "genderCategorization",'iccam_id' => "1",'iccam_name' => "Not Determined",'scart_field' => "GenderID",'scart_code' => "UN",],
            ['iccam_field' => "genderCategorization",'iccam_id' => "2",'iccam_name' => "Female",'scart_field' => "GenderID",'scart_code' => "FE",],
            ['iccam_field' => "genderCategorization",'iccam_id' => "3",'iccam_name' => "Male",'scart_field' => "GenderID",'scart_code' => "MA",],
            ['iccam_field' => "genderCategorization",'iccam_id' => "4",'iccam_name' => "Both",'scart_field' => "GenderID",'scart_code' => "BO",],
            ['iccam_field' => "virtualContentCategorization",'iccam_id' => "0",'iccam_name' => "No",'scart_field' => "IsVirtual",'scart_code' => "0",],
            ['iccam_field' => "virtualContentCategorization",'iccam_id' => "1",'iccam_name' => "Yes",'scart_field' => "IsVirtual",'scart_code' => "1",],
            ['iccam_field' => "userGeneratedContentCategorization",'iccam_id' => "0",'iccam_name' => "No",'scart_field' => "IsUserGC",'scart_code' => "0",],
            ['iccam_field' => "userGeneratedContentCategorization",'iccam_id' => "1",'iccam_name' => "Yes",'scart_field' => "IsUserGC",'scart_code' => "1",],
            ['iccam_field' => "childModelingCategorization",'iccam_id' => "0",'iccam_name' => "No",'scart_field' => "IsChildModeling",'scart_code' => "0",],
            ['iccam_field' => "childModelingCategorization",'iccam_id' => "1",'iccam_name' => "Yes",'scart_field' => "IsChildModeling",'scart_code' => "1",],
            ['iccam_field' => "sourceUrlSiteType",'iccam_id' => "2",'iccam_name' => "Website",'scart_field' => "SiteTypeID",'scart_code' => "webpage",],
            ['iccam_field' => "genderCategorization",'iccam_id' => "4",'iccam_name' => "Both",'scart_field' => "GenderID",'scart_code' => "FEMA",],
            ['iccam_field' => "actionReasons",'iccam_id' => "1",'iccam_name' => "Other",'scart_field' => "actionReasonID",'scart_code' => "1",],
            ['iccam_field' => "actionReasons",'iccam_id' => "2",'iccam_name' => "ContentNotFound",'scart_field' => "actionReasonID",'scart_code' => "2",],
            ['iccam_field' => "actionReasons",'iccam_id' => "3",'iccam_name' => "ReferrerNotKnownOrIncorrect",'scart_field' => "actionReasonID",'scart_code' => "3",],
            ['iccam_field' => "actionReasons",'iccam_id' => "4",'iccam_name' => "UnableToAccess",'scart_field' => "actionReasonID",'scart_code' => "4",],
            ['iccam_field' => "actionReasons",'iccam_id' => "5",'iccam_name' => "PasswordProtected",'scart_field' => "actionReasonID",'scart_code' => "5",],
            ['iccam_field' => "actionReasons",'iccam_id' => "6",'iccam_name' => "AccountDisabled",'scart_field' => "actionReasonID",'scart_code' => "6",],
            ['iccam_field' => "actionReasons",'iccam_id' => "7",'iccam_name' => "LegallyUnableToAccess",'scart_field' => "actionReasonID",'scart_code' => "7",],
            ['iccam_field' => "actionReasons",'iccam_id' => "8",'iccam_name' => "LinkExpired",'scart_field' => "actionReasonID",'scart_code' => "8",],
            ['iccam_field' => "actionReasons",'iccam_id' => "9",'iccam_name' => "Error404NotFoundOrSiteDown",'scart_field' => "actionReasonID",'scart_code' => "9",],
            ['iccam_field' => "actionReasons",'iccam_id' => "10",'iccam_name' => "Error503OrContentTemporarilyUnavailable",'scart_field' => "actionReasonID",'scart_code' => "10",],
            ['iccam_field' => "actionReasons",'iccam_id' => "11",'iccam_name' => "PremiumAccountNecessary",'scart_field' => "actionReasonID",'scart_code' => "11",],
            ['iccam_field' => "actionReasons",'iccam_id' => "12",'iccam_name' => "MissingPartsToOpenFile",'scart_field' => "actionReasonID",'scart_code' => "12",],
            ['iccam_field' => "actionReasons",'iccam_id' => "13",'iccam_name' => "ContentRemovedBeforeAssessment",'scart_field' => "actionReasonID",'scart_code' => "13",],
            ['iccam_field' => "actionReasons",'iccam_id' => "14",'iccam_name' => "AdultPornography",'scart_field' => "actionReasonID",'scart_code' => "14",],
            ['iccam_field' => "actionReasons",'iccam_id' => "15",'iccam_name' => "NudismOrNotSexualized",'scart_field' => "actionReasonID",'scart_code' => "15",],
            ['iccam_field' => "actionReasons",'iccam_id' => "16",'iccam_name' => "DoesNotMeetThresholdForIllegality",'scart_field' => "actionReasonID",'scart_code' => "16",],
            ['iccam_field' => "actionReasons",'iccam_id' => "17",'iccam_name' => "ImagesTooSmall",'scart_field' => "actionReasonID",'scart_code' => "17",],
            ['iccam_field' => "actionReasons",'iccam_id' => "18",'iccam_name' => "VirtualMaterial",'scart_field' => "actionReasonID",'scart_code' => "18",],
            ['iccam_field' => "actionReasons",'iccam_id' => "19",'iccam_name' => "LinkingOrLinklistsNotIllegal",'scart_field' => "actionReasonID",'scart_code' => "19",],
            ['iccam_field' => "actionReasons",'iccam_id' => "20",'iccam_name' => "AgeUnclearOrNotAssessable",'scart_field' => "actionReasonID",'scart_code' => "20",],
            ['iccam_field' => "actionReasons",'iccam_id' => "21",'iccam_name' => "AgeOverNationalThreshold",'scart_field' => "actionReasonID",'scart_code' => "21",],

        ]);

    }
}
