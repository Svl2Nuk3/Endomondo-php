<?php
/**
 * Copyright 2012  Alessandro Staniscia  (email : alessandro@staniscia.net)
 *
 *
 * User: staniscia
 * Date: 25/03/14
 * Time: 23.49
 *
 * This class derived by the project https://github.com/isoteemu/sports-tracker-liberator/blob/master/endomondo.py
 *
 *
 * Provides Endomondo API wrapper, which uses Endomondo mobile API
 * instead of HTML scrapping. Only some features are currently implemented,
 * but as Endomondo app uses HTTP, it's somewhat easy to implemented rest.
 * To retrieve network stream from from andoid, run on adb shell:
 * >>> tcpdump -n -s 0 -w -| nc -l -p 11233
 *
 * and on Linux:
 * >>> adb forward tcp:11233 tcp:11233
 * >>> nc 127.0.0.1 11233 | wireshark -k -S -i -
 *
 * To use, first authenticat client. Currently it seems that auth_token is
 * never changed, so only once is necessary and storing auth_toke somewhere
 * should be sufficient:
 *
 * >>> sports_tracker = Endomond('user@email.com', 'password')
 * >>> for workout in sports_tracker.workout_list():
 * >>> print workout.summary
 *
 * OR
 *
 * >>> sports_tracker = Endomond()
 * >>> sports_tracker.auth_token = '***blahblah***'
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 2.1 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <test://www.gnu.org/licenses/>.
 *
 *
 *
 * Class Endomondo_Proxy
 * @package net\staniscia\endomondo_php;
 */
namespace net\staniscia\endomondo_php;

use net\staniscia\endomondo_php\requests\Request;
use net\staniscia\endomondo_php\requests\Requests_Engine_Interface;
use net\staniscia\endomondo_php\requests\Response;
use net\staniscia\endomondo_php\utils\UUID;

require_once("utils/class-uuid.php");
require_once("class-user.php");
require_once("requests/class-requests-engine-interface.php");
require_once("class-sport-mapping.php");
require_once("class-workout-list.php");

class Endomondo_Proxy
{
    /*
    Well known urls.

    Well known urls for retrieving workout data from Endomondo. Thease are currently implementing version seven (:attribute:`Endomondo.app_version` ) of Endomondo App api, and those has been changed for version eight.

    Version 8 urls:
    test://api.mobile.endomondo.com/mobile/api/workout/get?authToken=<token>&fields=device,simple,basic,motivation,interval,hr_zones,weather,polyline_encoded_small,points,lcp_count,tagged_users,pictures,feed&workoutId=215638526&deflate=true&compression=deflate
    test://api.mobile.endomondo.com/mobile/api/workouts?authToken=<token>&fields=device,simple,basic,lcp_count&maxResults=20&deflate=true&compression=deflate

    :attribute:`Endomondo.URL_AUTH` Url for requesting authentication token.
    :attribute:`Endomondo.URL_WORKOUTS` Workouts (later in app called as "history" page) listing page.
    :attribute:`Endomondo.URL_TRACK` Running track
    :attribute:`Endomondo.URL_PLAYLIST` Music tracks

    */
    const URL_AUTH = 'https://api.mobile.endomondo.com/mobile/auth';
    const URL_WORKOUTS = 'http://api.mobile.endomondo.com/mobile/api/workout/list';
    const URL_TRACK = 'http://api.mobile.endomondo.com/mobile/readTrack';
    const URL_PLAYLIST = 'http://api.mobile.endomondo.com/mobile/api/workout/playlist';

    # Some parameters what Endomondo App sends.
    private $country = 'GB';
    private $device_id = null;
    private $os = "Android";
    private $app_version = "7.1";
    private $app_variant = "M-Pro";
    private $os_version = "2.3.7";
    private $model = "HTC Vision";

    # Auth token - seems to stay same, even when disconnecting - Security flaw in Endomondo side, but easy to fix on server side.
    private $user = null;
    private $requestsEngine = null;


    function __construct( Requests_Engine_Interface $requestsEngine)
    {
        $hostname = gethostname();
        $this->requestsEngine = $requestsEngine;
        $this->device_id = UUID::v5(UUID::NAMESPACE_DNS, $hostname);
        $this->user = new User();
        $this->requestsEngine->set_user_agent($this->get_user_agent());
    }

    private function  get_user_agent()
    {
        return sprintf("Dalvik/1.4.0 (Linux; U; %s %s; %s Build/GRI40)", $this->os, $this->os_version, $this->model);
    }

    /**
     * Logically connect with Endomondo server
     *
     * @param null $email
     * @param null $password
     * @return null|User
     */
    public function connect($email, $password)
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }
        $this->request_auth_token($email, $password);
        return $this->user;
    }

    public function isConnected()
    {
        return $this->user->is_valid();
    }

    public function get_user()
    {
        return $this->user;
    }

    /**
     * Logically diconnect with Endomondo server
     *
     */
    public function disconnect()
    {
        $this->user = new User();
    }

    /**
     *
     * Retrieve workouts summary.
     *
     * @param int $max_results Maximum number of workouts to be returned.
     * @param null $before datetime object or iso format date string (%Y-%m-%d %H:%M:%S UTC)
     * @return Workout_List
     */
    public function  get_workout_summary_list($max_results = 2, $before = null)
    {

        $params = array(
            'maxResults' => $max_results
        );

        if ($before == null) {
            $params['before'] = $before;
        }

        $response_body= $this->do_request(Endomondo_Proxy::URL_WORKOUTS, $params);

        return Workout_List::makeFromJson($response_body);
    }

    /**
     *
     * Retrieve workouts summary.
     *
     */
    public function  get_locations(Workout $workout )
    {

    }


    /**
     *    Request new authentication token from Endomondo server
     *
     * @param $email =  Email for login.
     * @param $password = Password for login.
     */
    private function request_auth_token($email, $password)
    {
        $data = array(
            'v' => "2.4",
            'action' => 'PAIR',
            'email' => $email,
            'password' => $password,
            'country' => $this->country,
            'deviceId' => $this->device_id,
            'os' => $this->os,
            'appVersion' => $this->app_version,
            'appVariant' => $this->app_variant,
            'osVersion' => $this->os_version,
            'model' => $this->model
        );

        $request=new Request();
        $request->url=Endomondo_Proxy::URL_AUTH;
        $request->queryParam=$data;

        $response = $this->requestsEngine->get($request);

        $result = $response->body;
        if ($this->startsWith($result, "OK")) {
            list($out, $action, $authToken, $measure, $displayName, $userId, $facebookConnected, $secureToken) = explode("\n", $result);
            list($dummy, $auth_token) = explode("=", $authToken);
            list($dummy, $display_name) = explode("=", $displayName);
            list($dummy, $user_id) = explode("=", $userId);
            list($dummy, $secure_token) = explode("=", $secureToken);
            $this->user = User::make($user_id, $display_name, $auth_token, $secure_token);
        } else {
            $this->disconnect();
        }

    }


    /**
     *
     * Helper for generating requests - can't be used in authentication.
     *
     * @param $url  base url for request. Well know are currently defined in :attribute:`Endomondo.URL_WORKOUTS` and :attribute:`Endomondo.URL_TRACK`.
     * @param array $params additional parameters to be passed in GET string.
     * @return string the body request
     * @throws \Exception as Exception('Not Connected')/Exception('Bed request! StatusCode:'.$request->status_code);
     */
    private function do_request($url, $data = array())
    {

        if (!$this->user->is_valid()) {
            throw new \Exception('Not Connected');
        }
        $data['authToken'] = $this->user->get_token();
        $data['language'] = 'EN';


        $request=new Request();
        $request->url=$url;
        $request->queryParam=$data;
        $response = $this->requestsEngine->get($request);


        if ($response->status_code != Response::OK) {
            throw new \Exception('Bed request! StatusCode: ' . $response->body);
        }
        return $response->body;
    }


    private function startsWith($haystack, $needle)
    {


        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

}