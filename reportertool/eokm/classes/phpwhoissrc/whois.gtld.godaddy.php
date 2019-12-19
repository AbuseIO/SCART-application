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

if (!defined('__GODADDY_HANDLER__'))
	define('__GODADDY_HANDLER__', 1);

require_once('whois.parser.php');

class godaddy_handler
	{
	function parse($data_str, $query)
		{

		// 2019/9/27/Gs: patched, format change of godaddy

		$items = array(
            //'owner' => 'Registrant:',
            //'admin' => 'Administrative Contact',
            //'tech' => 'Technical Contact',
            'owner' => 'Registrant Name:',
            'owner2' => 'Registrant Organization:',
            'admin' => 'Admin Name:',
            'tech' => 'Tech Name:',
            'domain.name' => 'Domain Name:',
            //'domain.nserver.' => 'Domain servers in listed order:',
            //'domain.created' => 'Created on:',
            //'domain.expires' => 'Expires on:',
            //'domain.changed' => 'Last Updated on:',
            //'domain.sponsor' => 'Registered through:'
            'domain.nserver.' => 'Name Server:',
            'domain.created' => 'Creation Date:',
            'domain.expires' => 'Registrar Registration Expiration Date:',
            'domain.changed' => 'Update Date:',
            'domain.sponsor' => 'Registrar:',
        );

        // 2019/9/27/Gs: fallback if var not set (found)

		$r = get_blocks($data_str, $items);
		$r['owner'] = (isset($r['owner']) ? get_contact($r['owner']) : (isset($r['owner2']) ? get_contact($r['owner2']) : 'Godaddy??' ) );
        $r['admin'] = (isset($r['admin']) ? get_contact($r['admin'],false,true) : '');
        $r['tech'] = (isset($r['tech']) ? get_contact($r['tech'],false,true) : '' );
		//$r['owner'] = get_contact($r['owner']);
		//$r['admin'] = get_contact($r['admin'],false,true);
		//$r['tech'] = get_contact($r['tech'],false,true);
		return format_dates($r, 'dmy');
		}
	}
?>
