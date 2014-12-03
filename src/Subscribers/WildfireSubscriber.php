<?php

namespace Behance\FireClient\Subscribers;

use Behance\FireClient\Consumers\ResponseConsumer;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\CompleteEvent;

/**
 * Provides connection mechanism for subscription and distribution of request events
 */
class WildfireSubscriber implements SubscriberInterface {

  private $_client_version = '0.7.4';

  /**
   * {@inheritDoc}
   */
  public function getEvents() {

    return [
        'before'   => [ 'onBefore' ],
        'complete' => [ 'onComplete' ]
    ];

  } // getEvents


  /**
   * Adds the FirePHP client user agent to the request headers, tricking
   * the receiver into returning wildfire headers
   *
   * @param GuzzleHttp\BeforeEvent $event
   */
  public function onBefore( BeforeEvent $event ) {

    $event->getRequest()->addHeader( 'User-Agent', 'FirePHP/' . $this->_client_version );

  } // onBefore


  /**
   * Consumes the response headers, proxying back to original caller
   *
   * @param GuzzleHttp\CompleteEvent $event
   */
  public function onComplete( CompleteEvent $event ) {

    $this->getConsumer()->run( $event );

  } // onComplete


  /**
   * @param FireClient\Consumers\ResponseConsumer $consumer
   */
  public function setConsumer( ResponseConsumer $consumer ) {

    $this->_consumer = $consumer;

  } // setConsumer


  /**
   * @return FireClient\Consumers\ResponseConsumer
   */
  public function getConsumer() {

    if ( empty( $this->_consumer ) ) {
      $this->setConsumer( new ResponseConsumer() );
    }

    return $this->_consumer;

  } // getConsumer

} // WildfireSubscriber
