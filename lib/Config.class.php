<?php

class Config
{
    public $local;
    public $certFile;
    public $certPwd;

    function __construct()
    {
        global $cfg;
        $this->local = $cfg['local'];
        $this->certFile = $cfg['cert_file'];
        $this->certPwd = $cfg['cert_pwd'];

        $this->pwdFile = $cfg['pwdFile'];
        $this->baseUrl = $cfg['baseUrl'];

    }

}