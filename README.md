# Sexual Child Abuse Reporting Tool (SCART)

This tool is development in name of the AbuseIO Foundation.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details.
You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

## AbuseIO

AbuseIO is a registered non-profit (ANBI) Foundation in the Netherlands
and as such we are required by law to publish information regarding the
Foundation and how funds are spend. AbuseIO is known under the dutch
RSIN number 855149012 and with the chamber of commerce registration
number 63234955.

## Goals and what we do

Our goal is to provide resources that help to combat internet abuse. We will try to achieve this by:

* Create Open Source Software (OSS) and tools based on Open Standards to help manage and detect abuse and help users to resolve internet abuse. This software must be easily installable and for anyone to use, from end users to large network operators or ISP’s
* Represent the interests of the abuse community
* Provide Education on combating internet abuse and promote the usage of resources to combat internet abuse.

See https://abuse.io

## System installation

* install WinterCMS (https://wintercms.com/docs/setup/installation)
* _(up and running wintercms installation with backend)_
* cd <root-project>/plugins
* git clone <repro> abuseio
* cd <root-project>/
* composer self-update --1 && composer update
* php artisan winter:up
  * _scart plugin running_
  * _scart settings for user logins_
  * _scart settings config_

