<?php

use Guzzle\Http\Client;
use FireClient\FirePHPSubscriber;


require( __DIR__ . "/../vendor/autoload.php" );

$guzzle = new Client("http://localhost");
$fireclient = new FirePHPSubscriber();

$guzzle->addSubscriber( $fireclient );


$response = $guzzle->get('/');

var_dump( $response->send() );
die;