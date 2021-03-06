<?php
/*
 * Copyright 2015 Scholica V.O.F.
 * Created by Matthijs Otterloo
 */

namespace Zermelo;

class Handler implements \Core\Handler {

    /**
     * @var ZermeloHelper
     */
    private $zermelo;

    /**
     * Handler name
     *
     * @return string
     */
    function handlerSlug() {
        return 'zermelo';
    }

    /**
     * Set user credentials
     *
     * @param $siteID
     * @param $username
     * @param $password
     */
    function setCredentials($siteID, $username, $password) {
        $this->zermelo = new ZermeloHelper($siteID);
        $this->zermelo->grabAccessToken($username, $password);
    }
    
    function getSchools(){
	    $domains = json_decode(file_get_contents('lib/Assets/zportal-domains.json'));
	    
	    $portals = array();
	    foreach($domains as $domain){
		    $domain = str_replace('.zportal.nl','', $domain);
		    $portals[] = array('site' => $domain, 'title' => strlen($domain) < 5 ? strtoupper($domain) : ucfirst($domain));
	    }
	    
	    return array('sites' => $portals);
    }

    /**
     * Get user info
     *
     * @return array
     */
    function getUserInfo() {
	    if(!$this->zermelo->token){
		    return array(
		        'provider_error' => 'Login details onjuist.'
	        );
	    }
	    $person = $this->zermelo->getPerson();
        if(!$person){
            return 403;
        }
        if($person->status == '404'){
            return array(
                'provider_error' => 'Deze user bestaat niet!'
            );
        }

        if($person->status == '401'){
	        return array(
		        'provider_error' => 'Gebruiker is niet ingelogd.'
	        );
        }

        $info = array(
            'name' => str_replace('  ', ' ', $person->firstName . ' ' . $person->prefix . ' ' . $person->lastName),
            'username' => $person->code
        );
        return $info;
    }

	function getUserPicture(){
		return 404;
	}

    /**
     * Get weekly shedule for a particular day
     *
     * @param $timestamp
     * @return array
     */
    function getSchedule($timestamp) {
        if(!$this->zermelo->token){
            return 403;
        }

        $subjects = (array) json_decode(file_get_contents('lib/Assets/subjects.json'));

        $tz = timezone_open('Europe/Amsterdam');
        $tz_offset = timezone_offset_get($tz, new \DateTime('@'.$timestamp, timezone_open('UTC')));

        $timestamp += $tz_offset+4;

        $weekstart = $this->getFirstDayOfWeek(date('Y', $timestamp), date('W', $timestamp));
        $weekend = strtotime('this Friday', $weekstart);

        $result = array(
            'week_timestamp' => $weekstart,
            'days' => array()
        );

        $curday = $weekstart;
        while($curday <= $weekend){
            $curwd = (int) date('w', $curday);
            $result['days'][$curwd] = array(
                'day_title' => $this->dutchDayName($curday),
                'day_ofweek' => (int)date('w', $curday),
                'items' => array()
            );
		
		$start = $curday; 
		$end = $curday + 86399;
        	$data = $this->zermelo->getStudentGrid($start, $end);

            	foreach($data as $item){
	            $item = (object)$item;
	            $start = ((int)$item->start);
                $vakname = isset($subjects[$item->subjects[0]]) ? $subjects[$item->subjects[0]] : $item->subjects[0];
                $teacher = $item->teachers[0];
                $cancelled = $item->cancelled;
                $moved   = $item->moved;
                
                $teacher = preg_replace('/^.*-\s*/', '', $teacher);

				if(empty($item->locations)){
					$item->locations = array('onbekend');
				}

                $result['days'][$curwd]['items'][] = array(
                    'title' => $vakname,
                    'subtitle' => 'Lokaal ' . $item->locations[0],
                    'teacher' => strtoupper($teacher),
                    'cancelled' => $cancelled,
                    'moved' => $moved,
                    'start' => $start,
                    'start_str' => date('H:i', $start+$tz_offset)
                );
            }

            $curday += 86400;
        }

        return $result;
    }

    private function dutchDayName($time){
        switch(date('N', $time)){
            case 1:
                return 'Maandag';
            case 2:
                return 'Dinsdag';
            case 3:
                return 'Woensdag';
            case 4:
                return 'Donderdag';
            case 5:
                return 'Vrijdag';
        }
    }

    private function getFirstDayOfWeek($year, $weeknr) {
        $offset = date('w', mktime(0, 0, 0, 1, 1, $year));
        $offset = ($offset < 5) ? 1 - $offset : 8 - $offset;
        $monday = mktime(0, 0, 0, 1, 1 + $offset, $year);
        return strtotime('+' . ($weeknr - 1) . ' weeks', $monday);
    }

}
