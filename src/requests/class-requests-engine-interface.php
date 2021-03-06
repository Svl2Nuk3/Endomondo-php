<?php
/**
Copyright 2012  Alessandro Staniscia  (email : alessandro@staniscia.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

User: staniscia
Date: 03/04/14
Time: 14.00

 */

namespace net\staniscia\endomondo_php\requests;

require_once('class-request.php');
require_once('class-response.php');

/**
 * Class Requests_Engine_Interface is a interface to hide the HTTP Requests engine used.
 *
 * On example dir you can find a example of implementation used to wrap the "Requests for PHP" ( http://requests.ryanmccue.info/ )
 *
 * @package net\staniscia\endomondo_php\requests
 */
interface Requests_Engine_Interface
{

    /**
     * Insert the user agent of request
     *
     * @param $userAgent
     * @return none
     */
    public function  set_user_agent($userAgent);

    /**
     * Execute a get request
     *
     * @param Request $theRequest
     * @return Response
     */
    public function  get(Request $theRequest);

}




