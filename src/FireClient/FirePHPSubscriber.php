<?php

namespace FireClient;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Guzzle\Common\Event;

class FirePHPSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {

    return [
        'request.before_send' => 'onBeforeSend',
        'request.complete'    => 'onComplete'
    ];


  } // getSubscribedEvents


  public function onBeforeSend( Event $event ) {

    $request = $event['request'];

    $request->addHeader( 'User-Agent', 'FirePHP/0.7.4' );

  } // onBeforeSend

  public function onComplete( Event $event ) {

    die("HERE");

  } // onComplete

} // FirePHPSubscriber