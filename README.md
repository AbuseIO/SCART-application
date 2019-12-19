# EOKM Reporting Tool (ERT)

This tool is development by the AbuseIO Foundation. 

AbuseIO is a registered non-profit (ANBI) Foundation in the Netherlands and as such we are required by law to publish information regarding the Foundation and how funds are spend. AbuseIO is known under the dutch RSIN number 855149012 and with the chamber of commerce registration number 63234955.

**The EOKM Reporting Tool (ERT) is specific version from the AbuseIO Reporting Tool (ART) made for EOKM.**

##Goals and what we do

Our goal is to provide resources that help to combat internet abuse. We will try to achieve this by:

* Create Open Source Software (OSS) and tools based on Open Standards to help manage and detect abuse and help users to resolve internet abuse. This software must be easily installable and for anyone to use, from end users to large network operators or ISP’s
* Represent the interests of the abuse community
* Provide Education on combating internet abuse and promote the usage of resources to combat internet abuse.

See https://abuse.io

## System Installation

* install OctoberCMS (Basic)
* install Rainlab.Builder plugin
* put \reportertool\* into plugins subdirectory of OctoberCMS
* go to Builder -> Version -> and commit last version
  - ERT menu's 
* Fill database:
  - develop\EOKM systemroles with autorization.sql
  - develop\EOKM email default layout.sql
  - develop\EOKM email template.sql
  - develop\EOKM grade questions.sql

* Fill Administrators (user)
  - scheduler login in group ERTscheduler
    - login and password put in .env
    - NOT in group ERKworkuser
  - users in group ERTadmin (all) or ERTmanager (analist)
    - group ERTworkuser 
  
* Scheduler; /etc/crontab
  - "* * * * * root php <source dir>artisan schedule:run --env=dev"
  
  
