<?php
/**
 * Created by PhpStorm.
 * User: Mhmd Backer Shehadi
 * Bitcko
 * http://www.bitcko.com
 * Date: 6/13/18
 * Time: 2:38 PM
 */

namespace bitcko\googlecalendar;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use yii\helpers\Url;
use Google_Service_Exception;

class GoogleCalendarApi
{

    private $username; // username use to create credentials to different users
    private $calendarId; // calendarId, id of the calendar
    private $client; // google client auth
    private $credentialsPath; // path to credentials


    /**
     * Constructor Setup an authorized API client.
     * @param $pathToClientSecret string
     * @param $username string
     * @param $calendarId string
     * @param $redirectUrl string
     */
    public function __construct($username,$calendarId='',$redirectUrl='')
    {
        $this->username = $username;
        $this->calendarId = $calendarId;
        $this->client = new Google_Client();
        $this->client->setApplicationName('Yii Google Calendar API');
        $this->client->setScopes(Google_Service_Calendar::CALENDAR);
        $this->client->setAuthConfig(\Yii::getAlias("@app/config/").'client_secret.json');
        $this->client->setRedirectUri($redirectUrl);
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt('force');
        $cred = 'google_api_tokens/'.$this->username .'_credentials.json';
        // Load previously authorized credentials from a file.
        $this->credentialsPath = $this->expandHomeDirectory($cred);
    }

    /**
     * generate api accessToken
     */
    public function generateGoogleApiAccessToken(){

        if ($this->checkIfCredentialFileExists()) {
            $accessToken = json_decode(file_get_contents($this->credentialsPath), true);
        } else {
            // Request authorization from the user.
            if(!isset($_GET['code'])){
                return \Yii::$app->controller->redirect( $this->client->createAuthUrl());
            }else{
                $authCode = $_GET['code'];
                // Exchange authorization code for an access token.
                $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
                // Store the credentials to disk.
                if (!file_exists(dirname($this->credentialsPath))) {
                    mkdir(dirname($this->credentialsPath), 0700, true);
                }
                file_put_contents($this->credentialsPath, json_encode($accessToken));
            }
        }
        $this->client->setAccessToken($accessToken);
        // Refresh the token if it's expired.
        // New check for passing the refresh token down.
        if ($this->client->isAccessTokenExpired()) {
            //Get the old access token
            $oldAccessToken=$this->client->getAccessToken();
            // Get new token with the refresh token
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            // Get the new access
            $accessToken=$this->client->getAccessToken();
            // Push the old refresh token to the new token
            $accessToken['refresh_token']=$oldAccessToken['refresh_token'];
            // Put to the new file
            file_put_contents($this->credentialsPath, json_encode($accessToken));
            //$this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            //file_put_contents($this->credentialsPath, json_encode($this->client->getAccessToken()));
        }

    }

    /**
     * Create Google Calendar Event.
     * @param object $event .
     * @return boolean(false) or Google Calendar Event.
     */
    public function createGoogleCalendarEvent($event){

        if ($this->checkIfCredentialFileExists() ) {
            if( $this->is_connected()){
                try{
                    $this->checkAccessToken();
                    $service = new Google_Service_Calendar($this->client);
                    $calendarId = $this->calendarId;
                    $event = new Google_Service_Calendar_Event(array(
                        'summary' => $event['summary'],
                        'location' => $event['location'],
                        'description' => $event['description'],
                        'start' =>$event['start'],
                        'end' => $event['end'],
                        'recurrence'=>$event['recurrence'],
                        'attendees' => $event['attendees'],
                        'reminders' => $event['reminders']
                    ));
                    return  $service->events->insert($calendarId, $event);
                }catch (Google_Service_Exception $e){
                    echo $e->getMessage();
                }
            }else{
                return false;
            }
        }
        return false;

    }

    /**
     * Delete Google Calendar Event.
     * @param string $eventId .
     * @return boolean.
     */
    public function deleteGoogleCalendarEvent($eventId){
        if ($this->checkIfCredentialFileExists() ) {
            if( $this->is_connected()){
                try{
                    $this->checkAccessToken();
                    $service = new Google_Service_Calendar($this->client);
                    $calendarId = $this->calendarId;
                    if($eventId and $service->events->get($calendarId, $eventId) ){
                        $service->events->delete($calendarId, $eventId);
                        return true;
                    }
                }catch (Google_Service_Exception $e){
                    echo $e->getMessage();
                }
            } else{
                return false;
            }
        }
        return false;
    }

    /**
     * User Calendar Lists (owner calendars), can use in html dropdown list.
     * @return  array.
     */
    public function calendarList(){
        $calendars = [];
        if ($this->checkIfCredentialFileExists() ) {
            if( $this->is_connected()){
                try {
                    $this->checkAccessToken();
                    $service = new Google_Service_Calendar($this->client);
                    $calendarList = $service->calendarList->listCalendarList();
                    foreach ($calendarList->getItems() as $calendarListEntry) {
                        if($calendarListEntry->getAccessRole()=="owner"){
                            $calendars [$calendarListEntry->getId()] = $calendarListEntry->getSummary();
                        }
                    }
                }catch (Google_Service_Exception $e){
                    echo $e->getMessage();
                }
            }
        }
        return $calendars;
    }
    /**
     * Expands the home directory alias '~' to the full path/ path to web dir in yii2 framework.
     * @param string $path the path to expand.
     * @return string the expanded path.
     */
    private function expandHomeDirectory($path)
    {

        return \Yii::getAlias("@app/config/").$path;
    }
    /**
     * Check if access token still valid.
     */
    private function checkAccessToken(){
        $accessToken = json_decode(file_get_contents($this->credentialsPath), true);
        $this->client->setAccessToken($accessToken);
        // Refresh the token if it's expired.
        if ($this->client->isAccessTokenExpired()) {
            //Get the old access token
            $oldAccessToken=$this->client->getAccessToken();
            // Get new token with the refresh token
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            // Get the new access
            $accessToken=$this->client->getAccessToken();
            // Push the old refresh token to the new token
            $accessToken['refresh_token']=$oldAccessToken['refresh_token'];
            // Put to the new file
            file_put_contents($this->credentialsPath, json_encode($accessToken));
            //$this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            //file_put_contents($this->credentialsPath, json_encode($this->client->getAccessToken()));
        }
    }

    /**
     * Check if there is internet connection.
     */
    private function is_connected()
    {
        $connected = @fsockopen("www.google.com", 80);
        //website, port  (try 80 or 443)
        if ($connected){
            $is_conn = true; //action when connected
            fclose($connected);
        }else{
            $is_conn = false; //action in connection failure
        }
        return $is_conn;
    }


    /**
     * Check if there is credential file exists.
     * @return boolean
     */
    public function checkIfCredentialFileExists(){
        if (file_exists($this->credentialsPath) ) {
            return true;
        }
        return false;
    }
}
