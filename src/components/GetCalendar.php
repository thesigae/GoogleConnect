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

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class GetCalendar extends \Cms\Classes\ComponentBase
{
    use \FireUnion\Signature\Traits\Signature;
    public function componentDetails()
    {
        return [
            'name' => 'Get Calendar',
            'description' => 'Get Google Calendar. This is used to call a specific calendar'
        ];
    }
    
    public function defineProperties()
    {
        return [
            'userId' => [
                 'title'             => 'User ID',
                 'description'       => 'The User Id to get the token for the calendar.',
                 'validationPattern' => '^[0-9]+$',
                 'validationMessage' => 'Can only be a number.'
            ],
            'calendarId' => [
                 'title'             => 'Calendar ID',
                 'description'       => 'The specific calendar to call by its id.'
            ]
        ];
    }
    
    public function onRun() {
    }
    
    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    
    private function getToken() {
        $user = $this->property('userId');
        return Client::where('userid', $user)->get()->first();
    }
    
    public function getCalendar() {
        $client = new Google_Client();
        $client->setAuthConfig(__DIR__.'/credentials.json');
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
            if ($this->property('calendarId')) {
                $calendarId = $this->property('calendarId');
            }
            $now = date('c');
            $dt = date("Y-m-01", strtotime($now));
            $firstDay = date('c', strtotime($dt));
            $optParams = array(
              'maxResults' => 25,
              'orderBy' => 'startTime',
              'singleEvents' => true,
              'timeMin' => $firstDay,
            );
            $results = $service->events->listEvents($calendarId, $optParams);
            $events = [];
            foreach ($results->getItems() as $key => $item) {
                $timestamp = strtotime($item->getStart()->dateTime);
                $dt = new DateTime();
                $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $dt->setTimestamp($timestamp);
                $now = new DateTime();
                $now->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $now->setTimestamp(strtotime('now'));
                $events[] = [
                    'dateDay' =>  $dt->format('d'),
                    'dateMonth' =>  $dt->format('m'),
                    'dateMonthDay' => $dt->format('m').'/'.$dt->format('d'),
                    'dateTime' => $dt->format('h:i a'),
                    'dateHour' => $dt->format('H'),
                    'thisHour' => $now->format('H'),
                    'summary' => $item->getSummary(),
                    'description' => $item->getDescription(),
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
            $client->setAuthConfig(__DIR__.'/credentials.json');
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
                if ($this->property('calendarId')) {
                    $calendarId = $this->property('calendarId');
                }
                $now = date('c');
                $dt = date("Y-m-01", strtotime($now));
                $firstDay = date('c', strtotime($dt));
                $optParams = array(
                  'maxResults' => 25,
                  'orderBy' => 'startTime',
                  'singleEvents' => true,
                  'timeMin' => $firstDay,
                );
                $event = $service->events->get($calendarId, $eventId['eid']);
                $originalEvent = $event;
                $startDate = substr($event->getStart()->getDateTime(), 0, -6);
                $endDate = substr($event->getEnd()->getDateTime(), 0, -6);
                $attendees = [];
                foreach ($event->getAttendees() as $at) {
                    $attendees[] = $at->getEmail();
                }
                $attendees = implode(",",$attendees);
                $event = [
                    'startDate' => $startDate,
                    'endDate' =>  $endDate,
                    'summary' => $event->getSummary(),
                    'description' => $event->getDescription(),
                    'link' => $event->getHtmlLink(),
                    'location' => $event->getLocation(),
                    'id' => $event->getId(),
                    'attendees' => $attendees,
                    'original' => $originalEvent
                    ];
                return $event;
                
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
                'success' => 'Canceled adding the event.'
            ])
        ];
    }
    
    public function onCancel() {
        return Redirect::to('/user/calendar');
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
                '#calendar' => $this->renderPartial('@createEvent.htm', [
                    'errors' => $validator->messages()
                ])
            ];
        } else {
            $client = new Google_Client();
            $client->setAuthConfig(__DIR__.'/credentials.json');
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
                if ($this->property('calendarId')) {
                    $calendarId = $this->property('calendarId');
                }
                $results = $service->events->insert($calendarId, $event);
                return [
                    '#calendar' => $this->renderPartial('@calendar.htm', [
                        'success' => 'Added the event successfully.'
                    ])
                ];
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
            $client->setAuthConfig(__DIR__.'/credentials.json');
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
                $event = $this->getEvent()['original'];
                $event->setSummary($inputs['summary']);
                $event->setAttendees($attendees);
                $event->getStart()->setDateTime($st);
                $event->getEnd()->setDateTime($ed);
                $event->setDescription($inputs['description']);
                $calendarId = 'primary';
                if ($this->property('calendarId')) {
                    $calendarId = $this->property('calendarId');
                }
                $updatedEvent = $service->events->update($calendarId, $event->getId(), $event);
                return [
                    '#calendar' => $this->renderPartial('@updateEvent.htm', [
                        'success' => 'Updated the event successfully.'
                    ])
                ];
            } else {
                return null;
            }
        }
    }

    
}// End of PHP class