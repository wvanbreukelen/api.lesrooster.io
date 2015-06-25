<?php
/*
 * Copyright 2015 Scholica V.O.F.
 * Created by Thomas Schoffelen
 */

namespace SOMtoday;

class Handler implements \Core\Handler {

    /**
     * @var SOMtodayHelper
     */
    private $somtoday;

    /**
     * Handler name
     *
     * @return string
     */
    function handlerSlug() {
        return 'somtoday';
    }

    /**
     * Set user credentials
     *
     * @param $siteID
     * @param $username
     * @param $password
     */
    function setCredentials($siteID, $username, $password) {
        $seg = explode('-', $siteID, 2);
        $this->somtoday = new SOMtodayHelper($seg[0], $seg[1]);
        $this->somtoday->getSession($username, $password);
    }

    /**
     * Get user info
     *
     * @return array
     */
    function getUserInfo() {
        $person = $this->somtoday->personData;
        if(!$person){
            return 403;
        }
        if($person == 'no_org'){
            return array(
                'provider_error' => 'Deze organisatie maakt geen gebruik van de SOMtoday ELO!'
            );
        }
        
        if($person == 'FEATURE_NOT_ACTIVATED'){
	        return array(
		        'provider_error' => 'Deze organisatie heeft gebruik van de SOMtoday API uitgeschakeld.'
	        );
        }
        
        if($person == 'FAILED_AUTHENTICATION'){
	        return array(
		        'provider_error' => 'Login details onjuist.'
	        );
        }
        
        if($person == 'FAILED_OTHER_TYPE'){
	        return array(
		        'provider_error' => 'Dit account soort is niet ondersteund.'
	        );
        }

        $info = array(
            'name' => $person->leerlingen[0]->fullName,
            'username' => $this->somtoday->sessionSlug()
        );
        return $info;
    }
    
     /**
     * Get grades
     *
     * @return array
     */
	function getGrades() {
	
	$subjects = (array) json_decode(file_get_contents('lib/Assets/subjects.json'));
	$data = $this->somtoday->request('Cijfers/GetMultiCijfersRecent/' . $this->somtoday->credentialsSequence() . '/' . $this->somtoday->personId)->data;

	if(!$this->somtoday->personData){
		return 403;
	}
	    
		foreach($data as $item){
	        $vakname = isset($subjects[$item->vak]) ? $subjects[$item->vak] : $item->vak;
	        $vakname = ucfirst($vakname);
	        $result['items'][] = array(
	        	'title' => $vakname,
	            'subtitle' => 'Weging: ' . $item->weging,
	            'description' => $item->beschrijving,
	            'grade' => $item->resultaat
	        );
	    }							
		return $result;
	}

    /**
     * Get user picture
     *
     * @return string
     */
    function getUserPicture() {
        if(!$this->somtoday->personData){
            return 403;
        }else {
            $image = $this->somtoday->request('pasfoto/pasfoto_leerling.jpg?id=' . $this->somtoday->personId);
            return $image;
        }
    }
    
    /**
     * Get weekly shedule for a particular day
     *
     * @param $timestamp
     * @return array
     */
    function getSchedule($timestamp) {
        if(!$this->somtoday->personData){
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

            $time = $curday * 1000;
            $data = $this->somtoday->request('Agenda/GetMultiStudentAgenda/' . $this->somtoday->credentialsSequence() . '/' . $time . '/' . $this->somtoday->personId)->data;

            foreach($data as $item){
                $start = ((int)$item->begin) / 1000;
                $vakname = isset($subjects[$item->vak]) ? $subjects[$item->vak] : $item->titel;
                $homework = $item->huiswerk;
                $teacher = $item->titel;
                $teacher = preg_replace('/^.*-\s*/', '', $teacher);
                if($item->lesuur && $item->lesuur != '-'){
                    $vakname = $item->lesuur . '. ' .  $vakname;
                }

                $result['days'][$curwd]['items'][] = array(
	                'hour' => $item->lesuur,
                    'title' => $vakname,
                    'subtitle' => 'Lokaal ' . $item->locatie,
                    'teacher' => $teacher,
                    'start' => $start,
                    'homework' => $homework,
                    'start_str' => date('H:i', $start+$tz_offset)
                );
            }

            $curday += 86400;
        }

        foreach($result['days'] as $index => $day){
            if(empty($day['items'])){
                unset($result['days'][$index]);
            }
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
