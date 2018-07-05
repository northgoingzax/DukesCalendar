<?php
namespace northgoingzax;

use Google_Config;
use Google_Client;
use Google_Auth_AssertionCredentials;
use Google_Service_Calendar;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_Event;
use Exception;
use DOMDocument;
use DateTime;
/**
 * Main class for project
 *
 * @author northgoingzax
 */
class DukesCalendar {
    /**
     * ID of the calendar
     * @var string 
     */
    private $calendar_id;
    
    /**
     * Google Service account email address / username
     * @var string
     */
    private $service_account;
    
    /**
     * Path to the p12 file
     * @var string
     */
    private $p12;
    
    /**
     * Path to the tmp directory
     * @var string
     */
    private $tmp;
    
    /**
     * Full URL of the page to scrape at the Dukes
     * @var string https://... 
     */
    public $url;
    
    /**
     * Google_Service_Calendar instance
     * @var object
     */
    public $Calendar;
    
    /**
     * The curl response of the Dukes cinema page
     * @var string
     */
    public $html;
    
    /**
     * The year and month, used for the post fields in curl request
     * @var string Y-m e.g. 2018-06
     */
    public $curl_date;
    
    /**
     * First of the current month
     * @var string Y-m-d e.g. 2018-06-01
     */
    public $calendar_date;
    
        
    public function __construct(array $config = []) {
        // Load all configuration settings
        try {
            $this->initilize($config);
        } catch (Exception $e) {
            die($e->getMessage());
        }
        
        // Create google config
        try {
            $this->_initializeAPI();
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    
    public function initilize(array $config) {
        // Check config file exists
        if(!file_exists(dirname(__DIR__) . '/config/app.php')) {
            throw new Exception("Configuration file missing");
        }
        
        // Configure default values
        $config_file = include dirname(__DIR__) . '/config/app.php';
        $this->_config($config_file);
        
        // Apply any additional user defined values
        if(!empty($config)) {
            $this->_config($config);
        }
        
        // Set the dates!
        $this->setDate('Today');
        
    }
    
    /**
     * Set the month for the query and the clearing of the calendar.
     * Defaults to current month.
     * @param string $date 'Today' to do next month, use 'Today + 1 month'
     */
    public function setDate(string $date = 'Today') {
        $this->curl_date = date('Y-m', strtotime($date));
        $this->calendar_date = $this->curl_date . '-01';
    }
    
    
    /**
     * The main method to run the entire sequence
     * Call when you are ready to populate the month
     */
    public function run() {
        // Run curl and save the results to a file
        try {
            $html = $this->runCurl();
        } catch (Exception $e) {
            die($e->getMessage());
        }
        
        // Parse the HTML
        try {
            $films = $this->parseHTML($html);
        } catch (Exception $e) {
            die($e->getMessage());
        }
        
        // Clear the month before re-adding everything
        if($this->clearMonth()) {
            $this->addFilms($films);
        }
        
    }
    
    public function listEvents($date) {
        $year = date('Y', strtotime($date));
        $month = date('m',strtotime($date));
        $time_min = $year . '-' . $month . '-01T0:00:00-00:00';
        $time_max = $year . '-' . $month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year) . 'T0:00:00-00:00';
        
        return $this->Calendar->events->listEvents($this->calendar_id, [
            'timeMin' => $time_min,
            'timeMax' => $time_max,
            'singleEvents' => true,
        ]);
    
    }
    
    public function clearMonth() {
        try {

            // Load events from Google
            $events = $this->listEvents($this->calendar_date);

            // Quit if no events
            if(count($events['items']) === 0) {
                return true;
            }

            // Delete all the events (in case stuff has changed)
            foreach($events['items'] as $val) {
                $this->Calendar->events->delete($this->calendar_id, $val['id']);
            }

            return true;
        } catch (Exception $e) {
            die("Unable to execute clearMonth function. Stopping script: " . $e->getMessage());
        }
    }
    
    public function addFilms(array $films = []) {
        if(empty($films)) {
            return true;
        }
        foreach($films as $val) {
            $this->addEvent($val['date'], $val['name'], $val['link']);    
            echo "\n$val[date] => $val[name]";
        }       
        
    }
    
    public function addEvent($date,$title,$description) {
        // Format end time for 2 hours after start
        $date_end = date('Y-m-d H:i:s', strtotime($date . "+2 hours"));
        
        // Format the start date for Google proper
        $dts = new DateTime($date);
        $start_date = (string) $dts->format(DateTime::RFC3339);
        
        // Format the end date fro Google proper
        $dte = new DateTime($date_end);
        $end_date = $dte->format(DateTime::RFC3339);

        // Format start date time into google calendar format
        $start = new Google_Service_Calendar_EventDateTime();
        $start->setDateTime($start_date);
        
        // Format end date time into google calendar format
        $end = new Google_Service_Calendar_EventDateTime();
        $end->setDateTime($end_date);
        
        // Create event entity
        $event = new Google_Service_Calendar_Event();
        $event->setSummary($title);
        $event->setLocation('The Dukes, Moor Lane, Lancaster LA1 1QE');
        $event->setStart($start);
        $event->setEnd($end);
        $event->setDescription($description);
        $this->Calendar->events->insert($this->calendar_id, $event);
       
    }
    

    
    private function _config(array $config) {
        if(empty($config)) {
            throw new Exception("config empty");
        }
        
        // Set all the variables from the config array
        foreach ($config as $key => $val) {
            $this->$key = $val;
        }
    }
    
    private function _initializeAPI() {
        // Load Google_Config
        try {
            $Google_Config = new Google_Config();
            $Google_Config->setClassConfig('Google_Cache_file', ['directory' => $this->tmp]);
        } catch (Exception $e) {
            throw new Exception("Unable to load Google_Config: " . $e->getMessage());
        }
        
        // Load Client
        try {
            $Google_Client = new Google_Client($Google_Config);	 	
        } catch (Exception $e) {
            throw new Exception("Unable to load Google_Client: " . $e->getMessage());
        }
        
        // Authenticate with client
        try {
            $Google_Client->setApplicationName("Client_Library_Examples");
                        
            // seproate additional scopes with a comma	 
            $scopes ="https://www.googleapis.com/auth/calendar"; 	
            $Google_Auth_AssertionCredentials = new Google_Auth_AssertionCredentials(	 
                $this->service_account,
                [$scopes],	 	
                file_get_contents($this->p12)
            );	 	
            $Google_Client->setAssertionCredentials($Google_Auth_AssertionCredentials);
            if($Google_Client->getAuth()->isAccessTokenExpired()) {	 	
                $Google_Client->getAuth()->refreshTokenWithAssertion($Google_Auth_AssertionCredentials);	 	
            }	 	
        } catch (Exception $e) {
            throw new Exception("Unable to authenticate with Google API: " . $e->getMessage());
        }
        
        // Connect to the calendar
        try {
            $this->Calendar = new Google_Service_Calendar($Google_Client);
        } catch (Exception $e) {
            throw new Exception("Unable to connect to Google_Service_Calendar: " . $e->getMessage());
        }
    }
    
    public function parseHTML(string $html = '') {
        if(empty($html)) {
            // Maybe runCurl has been called but not passed to the function
            // Check for class variable
            if(!empty($this->html)) {
                $html = $this->html;
            } else {
                throw new Exception("Nothing to parse. No HTML file created");
            }
        }
        
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        // https://stackoverflow.com/questions/6573258/domdocument-and-special-characters
        $dom->encoding = 'utf-8';
        $dom->loadHTML(utf8_decode($html));
        
        $body = $dom->getElementsByTagName('li');
        $body_array = iterator_to_array($body);
        
        // Declare array
        $films = [];
        
        // Cycle through each div
        foreach ($body_array as $div) {
            $film_name = $film_time = $date = $date2 = $spans = $h4 = null;

            if($div->getAttribute('class') === 'item event-item') {

                // Date time
                $spans = iterator_to_array($div->getElementsByTagName('span') );
                
                // Extract the date based on the layout
                // and convert it into Google appropriate format
                $film_time = $this->parseDate($spans);
                

                //Names
                $h4 = iterator_to_array($div->getElementsByTagName('h4') );
                $film_name = $this->parseName($h4);
                
                // Link to more details
                $link = $div->getElementsByTagName('a')->item(0)->getAttribute('href');

                $films[] = [
                    'name' => $film_name,
                    'date' => $film_time,
                    'link' => $link,
                ];
            }
        }
        
        // Set it to class variable
        $this->films = $films;
        
        // Return value
        return $films;
        
    }
    
    public function parseDate($spans) {
        foreach($spans as $node) {
            if(strpos($node->textContent, '|') !== false) {
                $date = $node->textContent;
            }
        }            
        // Date string comes in as: Monday | 3rd July 2018, 6:20pm
        // Need to convert this into something more like: 3rd July 2018 6:20pm
       
        
        // Remove pipe        
        $date = str_replace(" |", "", $date);
        // Remove comma
        $date = str_replace(",", "", $date);
        
        // Explode on the year so we have date and time separated
        $date2 = explode( date('Y') , $date);
        
        // date2: [0] => Monday 3rd July, [1] =>   6:20pm
        
        // Need to tidy up all the whitespace
        // Only have numbers, letters, or colon
        $date2[1] = preg_replace('/[^a-z0-9:-]+/', '', $date2[1]);
        
        // Put it all back together, using the year as glue
        $date =  implode( date('Y '), $date2);
        
        // Parse it for google
        return date("Y-m-d H:i:s", strtotime($date));
        
    }
    
    public function parseName($h4) {
        foreach($h4 as $tnode) {
            $film_name = $tnode->textContent;
        }
        return $film_name;
    }
    
    public function runCurl() {
        $_curl = curl_init();
        curl_setopt($_curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($_curl, CURLOPT_FAILONERROR, true);
        curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($_curl, CURLOPT_ENCODING, '');
        curl_setopt($_curl, CURLOPT_COOKIEFILE, './'.$this->root.'cookiePath.txt');
        curl_setopt($_curl, CURLOPT_COOKIEJAR, './'.$this->root.'cookiePath.txt');
        curl_setopt($_curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; InfoPath.1)');
        curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($_curl, CURLOPT_URL, $this->url);
        curl_setopt($_curl, CURLOPT_POST, 1);
        curl_setopt($_curl, CURLOPT_POSTFIELDS, "action=imic_event_grid&date=".$this->curl_date."&term=cinema");
    	$html = curl_exec( $_curl );    
        
        if(curl_error($_curl)) {
            throw new Exception("Curl Error: " . curl_error($_curl));
        }
        
        $this->html = $html;

        // Write the scrape to disk
        $fp = fopen('dukes.txt', 'w+');
        fwrite($fp, $html);
        
        return $html;
    }
    
    
}
