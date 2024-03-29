<?php
/*
Whois.php        PHP classes to conduct whois queries

Copyright (C)1999,2005 easyDNS Technologies Inc. & Mark Jeftovic

Maintained by David Saez

For the most recent version of this package visit:

http://www.phpwhois.org

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

require_once('whois.parser.php');

if (!defined('__BE_HANDLER__'))
	define('__BE_HANDLER__', 1);

class be_handler
	{
	function parse($data, $query)
		{
		$items = [
					'domain.name'		=> 'Domain:',
					'domain.status'	=> 'Status:',
					'domain.nserver'	=> 'Nameservers:',
					'domain.created'	=> 'Registered:',
					'owner'				=> 'Registrant:',
					'tech'				=> 'Registrar Technical Contacts:',
					'registrar'			=> 'Registrar:',
					//'agent.name'			=> 'Name:',
					'agent.uri'			=> 'Website:'
					];

		$trans = [
					'company name2:' => ''
					];

		$r['regrinfo'] = get_blocks($data['rawdata'], $items);

		if ($r['regrinfo']['domain']['status'] == 'NOT AVAILABLE')
			{
			$r['regrinfo']['registered'] = 'yes';
			$r['regrinfo'] = get_contacts($r['regrinfo'], $trans);

			if (isset($r['regrinfo']['registrar'])) {
				$sponsor = get_contact($r['regrinfo']['registrar'], $trans);
				if (isset($r['regrinfo']['agent'])) {
                    $r['regrinfo']['domain']['sponsor'] = array_merge($sponsor, $r['regrinfo']['agent']);
                    unset($r['regrinfo']['registrar']);
                    unset($r['regrinfo']['agent']);
                }
			}

			$r = format_dates($r, '-mdy');
			}
		else
			$r['regrinfo']['registered'] = 'no';

		$r['regyinfo']['referrer'] = 'http://www.domain-registry.nl';
		$r['regyinfo']['registrar'] = 'DNS Belgium';
		return $r;
		}
	}
