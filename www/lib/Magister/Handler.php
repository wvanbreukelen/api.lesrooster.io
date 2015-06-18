<?php
/*
 * Copyright 2015 Scholica V.O.F.
 * Created by Thomas Schoffelen
 */

namespace Magister;

class Handler implements \Core\Handler {

    /**
     * @var MagisterHelper
     */
    private $magister;

    /**
     * Handler name
     *
     * @return string
     */
    function handlerSlug() {
        return 'magister';
    }

    /**
     * Set user credentials
     *
     * @param $siteID
     * @param $username
     * @param $password
     */
    function setCredentials($siteID, $username, $password) {
        $this->magister = new MagisterHelper($siteID);
        $this->magister->getSession($username, $password);
    }

    /**
     * Get user info
     *
     * @return array
     */
    function getUserInfo() {
        $person = $this->magister->personData;
        if(!$person){
            return 403;
        }

        $info = array(
            'name' => str_replace('  ', ' ', $person->Roepnaam . ' ' . $person->Tussenvoegsel . ' ' . $person->Achternaam),
            'username' => $this->magister->sessionSlug()
        );
        return $info;
    }

    /**
     * Get user picture
     *
     * @return string
     */
    function getUserPicture() {
        if(!$this->magister->personData){
            return 403;
        }

        return $this->magister->personRequest('foto?width=300&height=300&crop=true');
    }

    /**
     * Get weekly shedule for a particular day
     *
     * @param $timestamp
     * @return array
     */
    function getSchedule($timestamp) {
        if(!$this->magister->personData){
            return 403;
        }

        $tz = timezone_open('Europe/Amsterdam');
        $tz_offset = timezone_offset_get($tz, new \DateTime('@'.$timestamp, timezone_open('UTC')));

        $timestamp += $tz_offset+4;

        $weekstart = $this->getFirstDayOfWeek(date('Y', $timestamp), date('W', $timestamp));
        $weekend = strtotime('this Friday', $weekstart);
        $data = $this->magister->personRequest('afspraken?status=1&tot=' . date('Y-m-d', $weekend) . '&van=' . date('Y-m-d', $weekstart))->Items;

        $result = array(
            'week_timestamp' => $weekstart,
            'days' => array()
        );

        $curday = $weekstart;
        while($curday <= $weekend){
            $result['days'][(int)date('w', $curday)] = array(
                'day_title' => $this->dutchDayName($curday),
                'day_ofweek' => (int)date('w', $curday),
                'items' => array()
            );
            $curday += 86400;
        }

        foreach($data as $item){
            $start = strtotime($item->Start) + $tz_offset;
            $curwd = date('w', $start);

            if($item->DuurtHeleDag){
                $result['days'][(int)$curwd]['items'][] = array(
                    'title' => $item->Omschrijving,
                    'subtitle' => $item->Lokatie,
                    'start' => $start,
                    'start_str' => 'DAG'
                );
            }else {
                $result['days'][(int)$curwd]['items'][] = array(
                    'title' => $item->LesuurVan . '. ' . $item->Vakken[0]->Naam,
                    'subtitle' => 'Lokaal ' . $item->Lokalen[0]->Naam,
                    'start' => $start,
                    'start_str' => date('H:i', $start+$tz_offset)
                );
            }
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
