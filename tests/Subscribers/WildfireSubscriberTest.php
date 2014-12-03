<?php

namespace Behance\FireClient\Subscribers;

use Behance\FireClient\Subscribers\WildfireSubscriber;
use Behance\FireClient\Consumers\ResponseConsumer;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Exception\RequestException;

use FirePHP as FirePHP;

class WildfireSubscriberTest extends \PHPUnit_Framework_TestCase {

  private $_target   = 'Behance\\FireClient\\Subscribers\\WildfireSubscriber',
          $_consumer = 'Behance\\FireClient\\Consumers\\ResponseConsumer';


  /**
   * @test
   */
  public function setterGetter() {

    $subscriber = new WildfireSubscriber();
    $consumer   = new ResponseConsumer();

    $this->assertInstanceOf( $this->_consumer, $subscriber->getConsumer() );

    $this->assertNotSame( $consumer, $subscriber->getConsumer() );

    $subscriber->setConsumer( $consumer );

    $this->assertSame( $consumer, $subscriber->getConsumer() );

  } // setterGetter

  /**
   * @test
   */
  public function getEvents() {

    $expected_events = [
        'before',
        'complete'
    ];

    $subscriptions = ( new WildfireSubscriber() )->getEvents();
    $this->assertNotEmpty( $subscriptions );

    $events = array_keys( $subscriptions );

    $this->assertNotEmpty( $events );

    foreach ( $expected_events as $expected_event ) {
      $this->assertContains( $expected_event, $events );
    }

  } // getEvents


  /**
   * Ensure events are registered, test they are called during request
   *
   * @test
   * @dataProvider httpCodeClassProvider
   */
  public function subscribeEvents( $http_code ) {

    $methods    = [ 'onBefore', 'onComplete' ];
    $subscriber = $this->getMock( $this->_target, $methods );

    $subscriber->expects( $this->once() )
      ->method( 'onBefore' )
      ->with( $this->isInstanceOf( 'GuzzleHttp\Event\BeforeEvent' ) );

    if ( $http_code < 400 ) {

      $subscriber->expects( $this->once() )
        ->method( 'onComplete' )
        ->with( $this->isInstanceOf( 'GuzzleHttp\Event\CompleteEvent' ) );

    } // if http_code < 400

    else {

      $subscriber->expects( $this->never() )
        ->method( 'onComplete' );

    } // else (>=400)

    $guzzle  = new Client();
    $mock    = new Mock( [ new Response( $http_code ) ] );
    $emitter = $guzzle->getEmitter();

    $emitter->attach( $mock );
    $emitter->attach( $subscriber );

    try {

      $guzzle->get( '/' );

      $this->assertLessThan( 400, $http_code );

    } // try

    catch( RequestException $e ) {
      // This is expected, but only for 400-500+ response codes
      $this->assertGreaterThanOrEqual( 400, $http_code );
    }

  } // subscribeEvents


  /**
   * Ensure events are registered, test they are called during request
   *
   * @test
   * @dataProvider httpCodeClassProvider
   */
  public function subscribeRun( $http_code ) {

    $name       = $this->_target;
    $subscriber = new $name();

    $guzzle     = new Client();
    $mock       = new Mock( [ new Response( $http_code ) ] );
    $consumer   = $this->getMock( 'Behance\\FireClient\\Consumers\\ResponseConsumer', [ 'run' ] );

    $subscriber->setConsumer( $consumer );

    if ( $http_code < 400 ) {

      $consumer->expects( $this->once() )
        ->method( 'run' )
        ->with( $this->isInstanceOf( 'GuzzleHttp\Event\CompleteEvent' ) );

    } // if http_code < 400

    else {

      $consumer->expects( $this->never() )
        ->method( 'run' );

    } // else (http_code >= 400)

    $emitter = $guzzle->getEmitter();
    $emitter->attach( $mock );
    $emitter->attach( $subscriber );

    try {
      $guzzle->get( '/' );

      $this->assertLessThan( 400, $http_code );
    }

    catch( RequestException $e ) {
      // This is expected, but only for 400-500+ response codes
      $this->assertGreaterThanOrEqual( 400, $http_code );
    }

  } // subscribeRun


  /**
   * Ensure events are registered, test they are called during request
   *
   * @test
   * @dataProvider httpCodeClassProvider
   */
  public function subscribe( $http_code ) {

    $firephp    = $this->getMock( 'FirePHP' );
    $subscriber = $this->getMock( $this->_target, null );

    $consumer   = $this->getMock( $this->_consumer, [], [ $firephp ] );
    $subscriber->setConsumer( $consumer );

    $guzzle     = new Client();
    $mock       = new Mock( [ new Response( $http_code ) ] );

    $emitter = $guzzle->getEmitter();
    $emitter->attach( $mock );
    $emitter->attach( $subscriber );

    try {
      $guzzle->get( '/' );

      $this->assertLessThan( 400, $http_code );
    }

    catch( RequestException $e ) {

      // This is expected, but only for 400-500+ response codes
      $this->assertGreaterThanOrEqual( 400, $http_code );

    } // catch RequestException

  } // subscribe


  /**
   * Provides an individual HTTP code for each major class of response type
   *
   * @return array
   */
  public function httpCodeClassProvider() {

    return [
        [ 200 ],
        [ 300 ],
        [ 400 ],
        [ 500 ],
    ];

  } // httpCodeClassProvider

} // WildfireSubscriberTest
