<?php
/*
 * Copyright 2015 Scholica V.O.F.
 * Created by Thomas Schoffelen
 */

namespace SOMtoday;

use Core\HTTPHelper;
use Core\RemoteSessionHelper;

class SOMtodayHelper extends HTTPHelper {

    use RemoteSessionHelper;

    public $cacheTime = 86400;

    private $username;
    private $password;
    private $subdomain;
    private $brin;
    private $httpDomain;

    public $sessionData;
    public $personData;
    public $personId;


    public function __construct($subdomain, $brin) {
        $this->subdomain = $subdomain;
        $this->brin = $brin;
        $this->httpDomain = 'https://' . $this->subdomain . '-elo.somtoday.nl/services/mobile/v10/';
    }

    public function request($method, $postData = array()) {
        $d = $this->http($this->httpDomain . $method, $postData);

        if ($d->http_code < 200 || $d->http_code >= 300) {
            return false;
        }

        if(strstr($d->content, 'organisatieSearchField')){
            return 'no_org';
        }

        $json_dec = @json_decode($d->content);

        return $json_dec ? $json_dec : $d->content;
    }

    protected function getAccountHelper() {
        if ($this->personData) {
            return $this->personData;
        }

        $this->personData = $this->request('Login/CheckMultiLogin/' . $this->credentialsSequence());

        if($this->personData == 'no_org'){
            return $this->personData;
        }
        
        if($this->personData == 'FEATURE_NOT_ACTIVATED'){
		return $this->personData;
		}
		
	    if($this->personData == 'FAILED_AUTHENTICATION'){
			return $this->personData;
		}
		
		if($this->personData == 'FAILED_OTHER_TYPE'){
			return $this->personData;
		}

        if (!isset($this->personData->leerlingen[0]) || !isset($this->personData->leerlingen[0]->leerlingId)) {
            return false;
        }

        return $this->personData;
    }

    public function sessionSlug($ext = null) {
        return 'som-' . str_replace('=', 'x', base64_encode($this->subdomain . $this->username . $this->password . (isset($ext) ? '-' . $ext : '')));
    }

    public function credentialsSequence() {
        return $this->username . '/' . $this->hashPassword($this->password) . '/' . $this->brin;
    }

    protected function hashPassword($password) {
        return bin2hex(base64_encode(sha1($password, true)));
    }

    public function getSession($username = null, $password = null) {
        if ($username) {
            $this->username = $username;
        }
        if ($password) {
            $this->password = $password;
        }

        $this->personData = $this->session($this->sessionSlug(), $this->cacheTime, array($this, 'getAccountHelper'));
        if ($this->personData && $this->personData != 'no_org') {
            $this->personId = $this->personData->leerlingen[0]->leerlingId;
        }
    }

}