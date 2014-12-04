<?php

namespace Behance\FireClient\Subscribers;

use Behance\FireClient\Consumers\ResponseConsumer;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EndEvent;

/**
 * Provides connection mechanism for subscription and distribution of request events
 */
class WildfireSubscriber implements SubscriberInterface {

  private $_client_version = '0.7.4', // @var string  which version of FirePHP client to present to remote services
          $_timer;                    // @var float

  /**
   * {@inheritDoc}
   */
  public function getEvents() {

    return [
        'before'   => [ 'onBefore' ],
        'end'      => [ 'onEnd' ]
    ];

  } // getEvents


  /**
   * Adds the FirePHP client user agent to the request headers, tricking
   * the receiver into returning wildfire headers
   *
   * @param GuzzleHttp\BeforeEvent $event
   */
  public function onBefore( BeforeEvent $event ) {

    $this->_timer = $this->_getMicrotime(); // Start the timer

    $event->getRequest()->addHeader( 'User-Agent', 'FirePHP/' . $this->_client_version );

  } // onBefore


  /**
   * Consumes the response headers, proxying back to original caller
   *
   * @param GuzzleHttp\EndEvent $event
   */
  public function onEnd( EndEvent $event ) {

    // Elapsed time: now - then
    $elapsed = $this->_getMicrotime() - $this->_timer;

    $this->getConsumer()->run( $event, $elapsed );

  } // onEnd


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


  /**
   * @return float
   */
  protected function _getMicrotime() {

    return microtime( true );

  } // _getMicrotime

} // WildfireSubscriber
