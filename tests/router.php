<?php

// Router script para o PHP Built-in Server em ambiente de testes
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../api/index.php';
