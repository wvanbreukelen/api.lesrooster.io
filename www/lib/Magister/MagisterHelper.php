<?php
/*
 * Copyright 2015 Scholica V.O.F.
 * Created by Thomas Schoffelen
 */

namespace Magister;

use Core\HTTPHelper;
use Core\RemoteSessionHelper;

class MagisterHelper extends HTTPHelper {

    use RemoteSessionHelper;

    public $cacheTime = 600;

    private $username;
    private $password;
    private $subdomain;
    private $httpDomain;

    public $sessionData;
    public $personData;
    public $personId;


    public function __construct($subdomain) {
        $this->subdomain = $subdomain;
        $this->httpDomain = 'https://' . $this->subdomain . '.magister.net/api/';
    }

    public function request($method, $postData = array(), $f = false) {
        $d = $this->http($this->httpDomain . $method, $postData, $this->sessionData);
        if ($d->http_code >= 403 && !$f) {
            $this->getSession();
            if ($this->sessionData) {
                return $this->request($method, $postData, true);
            }
        }
        if ($d->http_code < 200 || $d->http_code >= 300) {
            return false;
        }

        $json_dec = @json_decode($d->content);

        return $json_dec ? $json_dec : $d->content;
    }

    public function personRequest($method, $postData = array()) {
        return $this->request('personen/' . $this->personId . '/' . $method, $postData);
    }


    protected function getSessionHelper() {
        $d = $this->http($this->httpDomain . 'sessie', array(
            'Gebruikersnaam' => $this->username,
            'Wachtwoord' => $this->password,
            'IngelogdBlijven' => true
        ));

        if ($d->http_code < 200 || $d->http_code >= 300) {
            return false;
        }

        return $d->cookies;
    }

    protected function getAccountHelper() {
        if (!$this->sessionData) {
            return false;
        }

        $this->personData = $this->request('account')->Persoon;

        if (!isset($this->personData->Id)) {
            return false;
        }

        return $this->personData;
    }

    public function sessionSlug($ext = null) {
        return 'mag-' . str_replace('=', 'x', base64_encode($this->subdomain . $this->username . $this->password . (isset($ext) ? '-' . $ext : '')));
    }

    public function getSession($username = null, $password = null) {
        if ($username) {
            $this->username = $username;
        }
        if ($password) {
            $this->password = $password;
        }

        $this->sessionData = $this->session($this->sessionSlug(), $this->cacheTime, array($this, 'getSessionHelper'));
        $this->personData = $this->session($this->sessionSlug('account'), $this->cacheTime, array($this, 'getAccountHelper'));
        if ($this->personData) {
            $this->personId = (int)$this->personData->Id;
        }
    }

}