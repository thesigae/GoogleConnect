<?php namespace BranMuffin\GoogleConnect\Components;

use Session;
use Cache;
use Input;
use Crypt;
use Redirect;
use Illuminate\Contracts\Encryption\DecryptException;
use October\Rain\Support\Collection;
use Illuminate\Http\Request;
use DateTime;
use DateTimeZone;
use Auth;
use Validator;

use Renatio\Dynamicpdf\Models\Template;

use BranMuffin\GoogleConnect\Models\Client;
use BranMuffin\GoogleConnect\Models\Settings;
use BranMuffin\GoogleConnect\Models\Configs;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class UserCalendar extends \Cms\Classes\ComponentBase
{
    use \FireUnion\Signature\Traits\Signature;
    public function componentDetails()
    {
        return [
            'name' => 'User Calendar',
            'description' => 'Require user to register their Gmail to access their primary calendar.'
        ];
    }
    
    public function defineProperties() {
        return [
            'getConfigId' => [
                'title' => 'Enter the config ID',
            ],
            'maxEvents' => [
                 'title'             => 'Max Events',
                 'description'       => 'The most amount of calendar events allowed',
                 'default'           => 25,
                 'type'              => 'string',
                 'validationPattern' => '^[0-9]+$',
                 'validationMessage' => 'The Max Events property can contain only numeric symbols'
            ],
            'pastDays' => [
                 'title'             => 'Past Days',
                 'description'       => 'The amount of past days to look for calendar events.',
                 'default'           => 30,
                 'type'              => 'string',
                 'validationPattern' => '^[0-9]+$',
                 'validationMessage' => 'The Max Items property can contain only numeric symbols'
            ],
        ];
    }
    
    public function onRun() {
    }
    
    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    public function onCheckEmail()
    {
        return [ 'isTaken' => Auth ::findUserByLogin(post
    ( 'email' )) ? 1 : 0 ];
    }
    
    public function getSuccess() {
        $success = Session::get('success');
        return $success;
    }
     
    public function getClient()
    {   
        $client = new Google_Client();
        $client->setAuthConfig(json_decode($this->getConfig(), true));
        $client->setAccessType("offline");        // offline access
        $client->setApprovalPrompt("force");
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setRedirectUri('https://dev.deltaliquidenergy.com/dev/authenticate/calendar');
        
        $auth_url = $client->createAuthUrl();
        
        header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
        
        return $auth_url;
    }
    
    private function getToken() {
        $user = Auth::getUser()->id;
        return Client::where('userid', $user)->get()->first();
    }
    
    private function getConfig() {
        if ($this->property('getConfigId')) {
            $row = Configs::find($this->property('getConfigId'));
            $config = new Collection([
                'web' => [
                    'client_id' => $row->clientid,
                    'project_id' => $row->projectid,
                    'auth_uri' => $row->authuri,
                    'token_uri' => $row->tokenuri,
                    'auth_provider_x509_cert_url' => $row->certurl,
                    'client_secret' => $row->clientsecret,
                    'redirect_uris' => [ $row->redirecturis ],
                    'javascript_orgins' => [ $row->javascriptorigins ]
                ]
            ]);
        } else {
            $config = new Collection([
                'web' => [
                    'client_id' => Settings::get('calendar_client_id'),
                    'project_id' => Settings::get('calendar_project_id'),
                    'auth_uri' => Settings::get('calendar_auth_uri'),
                    'token_uri' => Settings::get('calendar_token_uri'),
                    'auth_provider_x509_cert_url' => Settings::get('calendar_cert_url'),
                    'client_secret' => Settings::get('calendar_client_secret'),
                    'redirect_uris' => [ Settings::get('calendar_redirect_uris') ],
                    'javascript_orgins' => [ Settings::get('calendar_javascript_origins') ]
                ]
            ]);
        }
        return $config->toJson();
    }
    
    public function getCalendar() {
        $client = new Google_Client();
        $client->setAuthConfig(json_decode($this->getConfig(), true));
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $user = $this->getToken();
        if ($user) {
            $token = $user->token;
        } else {
            $token = null;
        }
        if ($token) {
            $client->setAccessToken($token);
            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            }
            $service = new Google_Service_Calendar($client);
            $calendarId = 'primary';
            $firstDay = date('c', strtotime("-".$this->property('pastDays')." days"));
            $optParams = array(
              'maxResults' => $this->property('maxEvents'),
              'orderBy' => 'startTime',
              'singleEvents' => true,
              'timeMin' => $firstDay,
            );
            $results = $service->events->listEvents($calendarId, $optParams);
            $events = [];
            foreach ($results->getItems() as $key => $item) {
                if ($item->getStart()->date == null) {
                    $timestamp = strtotime($item->getStart()->dateTime);
                    $timezone = 'America/Los_Angeles';
                } else {
                    $timestamp = strtotime($item->getStart()->date);
                    $timezone = 'UTC';
                }
                if ($item->getEnd()->date == null) {
                    $endTime = strtotime($item->getEnd()->dateTime);
                    $timezone = 'America/Los_Angeles';
                } else {
                    $endTime = strtotime($item->getEnd()->date);
                    $timezone = 'UTC';
                }
                $dt = new DateTime();
                $dt->setTimezone(new DateTimeZone($timezone));
                $dt->setTimestamp($timestamp);
                $et = new DateTime();
                $et->setTimezone(new DateTimeZone($timezone));
                $et->setTimestamp($endTime);
                $events[] = [
                    'dateDay' =>  $dt->format('d'),
                    'dateMonth' =>  $dt->format('m'),
                    'dateYear' => $dt->format('Y'),
                    'dateFullDate' => $dt->format('Y-m-d\TH:i:s'),
                    'dateEndDate' => $et->format('Y-m-d\TH:i:s'),
                    'eventDate' => $this->property('maxEvents'),
                    'dateTime' => $dt->format('h:i a'),
                    'summary' => $item->getSummary(),
                    'link' => $item->getHtmlLink(),
                    'id' => $item->getId()
                    ];
            }
            return $events;
            
        } else {
            return null;
        }
    }
    
    public function getEvent() {
        $eventId = Input::all();
        if (isset($eventId['eid'])) {
            $client = new Google_Client();
            $client->setAuthConfig(json_decode($this->getConfig(), true));
            $client->setScopes(Google_Service_Calendar::CALENDAR);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            $user = $this->getToken();
            if ($user) {
                $token = $user->token;
            } else {
                $token = null;
            }
            if ($token) {
                $client->setAccessToken($token);
                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                }
                $service = new Google_Service_Calendar($client);
                $calendarId = 'primary';
                $event = $service->events->get($calendarId, $eventId['eid']);
                
                if ($event->getStart()->date == null) {
                    $timestamp = strtotime($event->getStart()->dateTime);
                    $timezone = 'America/Los_Angeles';
                } else {
                    $timestamp = strtotime($event->getStart()->date);
                    $timezone = 'UTC';
                }
                if ($event->getEnd()->date == null) {
                    $endTime = strtotime($event->getEnd()->dateTime);
                    $timezone = 'America/Los_Angeles';
                } else {
                    $endTime = strtotime($event->getEnd()->date);
                    $timezone = 'UTC';
                }
                $dt = new DateTime();
                $dt->setTimezone(new DateTimeZone($timezone));
                $dt->setTimestamp($timestamp);
                $et = new DateTime();
                $et->setTimezone(new DateTimeZone($timezone));
                $et->setTimestamp($endTime);
                
                $item = [
                    'dateFullDate' => $dt->format('Y-m-d\TH:i:s'),
                    'dateEndDate' => $et->format('Y-m-d\TH:i:s'),
                    'dateTime' => $dt->format('h:i a'),
                    'description' => $event->description,
                    'location' => $event->location,
                    'summary' => $event->getSummary(),
                    'link' => $event->getHtmlLink(),
                    'id' => $event->getId()
                    ];
                
                return $item;
                
            } else {
                return null;
            }
        }
    }
    
    public function onCreate() {
        return;
    }
    
    public function onUpdate() {
        return [
            '#calendar' => $this->renderPartial('@updateEvent.htm', [
                'success' => 'Edited the event successfully.'
            ])
        ];
    }
    
    public function onCancel() {
        return Redirect::to('/calendar')->with('message', 'Canceled adding the event');
    }
    
    public function onCreateEvent() {
        $inputs = Input::all();
        $validator = Validator::make(
            [
                'startdate' => $inputs['startDate'],
                'enddate' => $inputs['endDate']
            ],
            [
                'startdate' => 'required',
                'enddate' => 'required'
            ]
        );
        if ($validator->fails()) {
            return [
                '#error' => $this->renderPartial('@createEvent.htm', [
                    'errors' => $validator->messages()
                ])
            ];
        } else {
            $client = new Google_Client();
            $client->setAuthConfig(json_decode($this->getConfig(), true));
            $client->setScopes(Google_Service_Calendar::CALENDAR);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            $service = new Google_Service_Calendar($client);
            $user = $this->getToken();
            $token = $user->token;
            if ($token) {
                $client->setAccessToken($token);
                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                }
                if ($inputs['tos'] != null) {
                    $tos = explode(',', $inputs['tos']);
                    $tos = $tos + ['email' => Auth::getUser()['email']];
                    $attendees = [];
                    foreach($tos as $to) {
                        $attendees[] = ['email' => $to];    
                    }
                } else {
                    $attendees = array(
                        array('email' => Auth::getUser()['email'])
                        );
                }
                $st = new DateTime($inputs['startDate'], new DateTimeZone('America/Los_Angeles'));
                $st = $st->format(DateTime::ATOM);
                $ed = new DateTime($inputs['endDate'], new DateTimeZone('America/Los_Angeles'));
                $ed = $ed->format(DateTime::ATOM);
                $event = new Google_Service_Calendar_Event(array(
                      'summary' => $inputs['summary'],
                      'location' => $inputs['location'],
                      'description' => $inputs['description'],
                      'start' => array(
                        'dateTime' => $st,
                        'timeZone' => 'America/Los_Angeles',
                      ),
                      'end' => array(
                        'dateTime' => $ed,
                        'timeZone' => 'America/Los_Angeles',
                      ),
                      'attendees' => $attendees,
                      'reminders' => array(
                        'useDefault' => FALSE,
                      ),
                    ));
                $calendarId = 'primary';
                $event = $service->events->get($calendarId, $event['eid']);
                $results = $service->events->insert($calendarId, $event);
                return Redirect::refresh()->with('success', 'Added the event successfully.');
            } else {
                return null;
            }
        }
    }
    
    public function onUpdateEvent() {
        $inputs = Input::all();
        $validator = Validator::make(
            [
                'startdate' => $inputs['startDate'],
                'enddate' => $inputs['endDate']
            ],
            [
                'startdate' => 'required',
                'enddate' => 'required'
            ]
        );
        if ($validator->fails()) {
            return [
                '#calendar' => $this->renderPartial('@updateEvent.htm', [
                    'errors' => $validator->messages()
                ])
            ];
        } else {
            $client = new Google_Client();
            $client->setAuthConfig(json_decode($this->getConfig(), true));
            $client->setScopes(Google_Service_Calendar::CALENDAR);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            $service = new Google_Service_Calendar($client);
            $user = $this->getToken();
            $token = $user->token;
            if ($token) {
                $client->setAccessToken($token);
                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                }
                if ($inputs['tos'] != null) {
                    $tos = explode(',', $inputs['tos']);
                    $tos = $tos + ['email' => Auth::getUser()['email']];
                    $attendees = [];
                    foreach($tos as $to) {
                        $attendees[] = ['email' => $to];    
                    }
                } else {
                    $attendees = array(
                        array('email' => Auth::getUser()['email'])
                        );
                }
                $st = new DateTime($inputs['startDate'], new DateTimeZone('America/Los_Angeles'));
                $st = $st->format(DateTime::ATOM);
                $ed = new DateTime($inputs['endDate'], new DateTimeZone('America/Los_Angeles'));
                $ed = $ed->format(DateTime::ATOM);
                $eventId = $this->getEvent()['id'];
                $calendarId = 'primary';
                $event = $service->events->get($calendarId, $eventId);
                $event->setSummary($inputs['summary']);
                $event->setLocation($inputs['location']);
                $event->setDescription($inputs['description']);
                $event->start->setDateTime($st);
                $event->end->setDateTime($ed);
                $event->setAttendees($attendees);
                $results = $service->events->update($calendarId, $eventId, $event);
                return Redirect::refresh()->with('success', $results);
            } else {
                return null;
            }
        }
    }

    
}// End of PHP class