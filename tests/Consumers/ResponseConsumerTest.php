<?php

namespace Behance\FireClient\Subscribers;

use Behance\FireClient\Subscribers\WildfireSubscriber;
use Behance\FireClient\Consumers\ResponseConsumer;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Exception\RequestException;

use FirePHP as FirePHP;

class ResponseConsumerTest extends \PHPUnit_Framework_TestCase {

  private $_target    = 'Behance\\FireClient\\Consumers\\ResponseConsumer',
          $_wf_header = 'X-Wf-1-1-1-1';


  /**
   * @test
   * @dataProvider nonWildfireHeaderProvider
   */
  public function proxyNonWildfire( $header ) {

    $response = new Response( 200, $header );
    $consumer = $this->getMock( $this->_target, [ '_proxy' ] );

    $consumer->expects( $this->never() )
      ->method( '_proxy' );

    $consumer->proxyResponseHeaders( $response );

  } // proxyNonWildfire


  /**
   * @return array
   */
  public function nonWildfireHeaderProvider() {

    return [
        [ [ 'abc'           => 'xyx' ] ],
        [ [ 'X-fr-abc'      => 'xyx' ] ],
        [ [ 'Authorization' => '123456789' ] ]
    ];

  } // nonWildfireHeaderProvider


  /**
   * @test
   * @dataProvider wildfireHeaderProvider
   */
  public function proxyWildfire( $header, $type, $expected ) {

    $prefix = '[NEW PREFIX]';
    $client = $this->getMock( 'FirePHP', [ $type ] );

    $client->expects( $this->once() )
      ->method( $type )
      ->with( $prefix . ' ' . $expected );

    $response = new Response( 200, $header );
    $consumer = $this->getMock( $this->_target, null, [ $client ] );

    $consumer->setRemotePrefix( $prefix );
    $consumer->proxyResponseHeaders( $response );

  } // proxyWildfire


  /**
   * @test
   * @dataProvider wildfireTableHeaderProvider
   */
  public function proxyWildfireTable( $header, $label = '' ) {

    $prefix = '[NEW PREFIX]';
    $client = $this->getMock( 'FirePHP', [ 'table', 'warn' ] );

    if ( !empty( $label ) ) {

      $client->expects( $this->once() )
        ->method( 'table' )
        ->with( $prefix . ' ' . $label, $this->isType( 'array' ) );

      $client->expects( $this->never() )
        ->method( 'warn' );

    } // if label

    else {

      // Missing a label is an error condition
      $client->expects( $this->once() )
        ->method( 'warn' );

      $client->expects( $this->never() )
        ->method( 'table' );

    } // else

    $headers  = [ 'X-Wf-1-2-3-4' => $header ];
    $response = new Response( 200, $headers );
    $consumer = $this->getMock( $this->_target, null, [ $client ] );

    $consumer->setRemotePrefix( $prefix );
    $consumer->proxyResponseHeaders( $response );

  } // proxyWildfireTable


  /**
   * @return array
   */
  public function wildfireTableHeaderProvider() {

    $data   = [];
    $data[] = [ 'Key', 'Value' ];
    $data[] = [ 'abc', 123 ];

    $label  = 'ABC';

    $table_label = [ 'Label' => $label ];

    return [
        [ $this->_buildWildfireValue( FirePHP::TABLE, $data, $table_label ), $label ],
        [ $this->_buildWildfireValue( FirePHP::TABLE, $data, [] ), '' ]
    ];

  } // wildfireTableHeaderProvider

  /**
   * Same test as below, but captures request at client level instead of during reporting
   * @test
   */
  public function runWildfireErrorWarn() {

    $prefix  = '[PREFIX]';
    $client  = $this->getMock( 'FirePHP', [ 'table', 'warn' ] ); // Table is included for the initial publishing of the request

    $client->expects( $this->once() )
      ->method( 'table' );

    $client->expects( $this->once() )
      ->method( 'warn' )
      ->with( $this->stringStartsWith( $prefix . ' ' ) );

    $headers  = [ $this->_wf_header => 'abcdefg' ];

    $mock     = new Mock( [ new Response( 200, $headers ) ] );
    $consumer = $this->getMock( $this->_target, null, [ $client ] );
    $consumer->setRemotePrefix( $prefix );

    $client = $this->_buildClient( $consumer, $mock );
    $client->get( '/' );

  } // runWildfireErrorWarn


  /**
   * @test
   * @dataProvider wildfireErrorPayloadProvider
   */
  public function runWildfireError( $payload, $expected_error ) {

    $console = $this->getMock( 'FirePHP' );
    $headers = [ $this->_wf_header => $payload ];

    $response  = new Response( 200, $headers );
    $mock      = new Mock( [ $response ] );

    $consumer  = $this->getMock( $this->_target, [ '_reportParseError' ], [ $console ] );

    $consumer->expects( $this->once() )
      ->method( '_reportParseError' )
      ->with( $expected_error );

    $client = $this->_buildClient( $consumer, $mock );
    $client->get( '/' );

  } // runWildfireError


  /**
   * @return array
   */
  public function wildfireErrorPayloadProvider() {

    return [
        [ '1234|[{"Type":"INFO"},"Message with wrong size"]|', ResponseConsumer::ERROR_BAD_SIZE ],
        [ '26|abcdefghijklmnopqrstuvwxyz|', ResponseConsumer::ERROR_UNABLE_TO_DECODE ],
        [ '61|[{"Type":"INFO"},"Message with wrong size","Another section"]|', ResponseConsumer::ERROR_WRONG_DATA_COUNT ],
        [ '61|[{"Type":"INFO"},"Message with wrong size","Another section"]|', ResponseConsumer::ERROR_WRONG_DATA_COUNT ],
        [ '43|[{"Size":"Full"},"Message with wrong size"]|', ResponseConsumer::ERROR_MISSING_TYPE ],
        [ '49|[{"Type":"TABLE"},"Message that is not an array"]|', ResponseConsumer::ERROR_TABLE_WRONG_DATA ],
        [ '49|[{"Type":"TRACE"},"Message that is not an array"]|', ResponseConsumer::ERROR_TABLE_WRONG_DATA ],
        [ '49|[{"Type":"AAAAA"},"Message that is not an array"]|', ResponseConsumer::ERROR_UNHANDLED_TYPE ]
    ];

  } // wildfireErrorPayloadProvider


  /**
   * @return array
   */
  public function wildfireHeaderProvider() {

    $message = 'Message to be placed in the body of the log';
    $prefix  = 'X-Wf-';
    $results = [];
    $types   = [
        FirePHP::LOG,
        FirePHP::INFO,
        FirePHP::WARN,
        FirePHP::ERROR
    ];

    foreach ( $types as $ix => $type ) {

      $lower_type = strtolower( $type );
      $payload    = $this->_buildWildfireValue( $lower_type, $message );

      $results[ $type ] = [ [ $prefix . '1-2-3-' . ( $ix + 1 ) => $payload ], $lower_type, $message ];

    } // foreach types

    // In last case, message is not a string, but an array
    $message       = [ $message ];
    $lower_type    = strtolower( FirePHP::LOG );
    $array_payload = $this->_buildWildfireValue( $lower_type, $message );

    // Expected value is output of the var_export of it
    $results['Array'] = [ [ $prefix . '1-2-3-' . ( $ix + 1 ) => $array_payload ], $lower_type, var_export( $message, 1 ) ];

    return $results;

  } // wildfireHeaderProvider


  /**
   * @test
   */
  public function publishRequestNoResponse() {

    $method   = 'PATCH';
    $host     = 'abc.com';
    $protocol = 'https';
    $url      = $protocol . '://' . $host;

    $request_body = 'abcdefghj';
    $client       = new Client();
    $request      = $client->createRequest( $method, $url, [ 'body' => $request_body ] );
    $console      = $this->getMock( 'FirePHP', [ 'table' ] );
    $called_with  = [];

    $console->expects( $this->once() )
      ->method( 'table' )
      ->will( $this->returnCallback( function( $message, $table ) use ( &$called_with ) {
        $called_with = [ $message, $table ];
      } ) );

    $consumer = new ResponseConsumer( $console );

    // Reading here should not affect the read() call within publishRequest()
    $request->getBody()->read( 10 );

    $consumer->publishRequest( $request );

    // Extract parameters out of callback injection
    list( $message, $table ) = $called_with;

    $this->assertContains( $url, $message );
    $this->assertContains( $method, $message );

    $table_keys = [];
    $reformat   = [];

    foreach ( $table as $item ) {
      $table_keys[] = $item[0];
      $reformat[ $item[0] ] = $item[1];
    }

    $required = [ 'Key', 'Phrase', 'Host', 'Protocol', 'Request', 'Response' ];

    foreach ( $required as $require ) {
      $this->assertContains( $require, $table_keys );
    }

    $this->assertEquals( ResponseConsumer::ERROR_NO_RESPONSE, $reformat['Phrase'] );
    $this->assertEquals( $host, $reformat['Host'] );
    $this->assertEquals( $protocol, $reformat['Protocol'] );
    $this->assertEquals( $request_body, $reformat['Request'] );

  } // publishRequestNoResponse


  /**
   * @test
   */
  public function runNoResponse() {

    $request = new Request( 'GET', 'abc.com' );
    $event   = $this->getMockBuilder( 'GuzzleHttp\Event\EndEvent' )
      ->disableOriginalConstructor()
      ->setMethods( [ 'getRequest', 'getResponse' ] )
      ->getMock();

    $event->expects( $this->once() )
      ->method( 'getRequest' )
      ->will( $this->returnValue( $request ) );

    // Simulate failed request
    $event->expects( $this->once() )
      ->method( 'getResponse' )
      ->will( $this->returnValue( null ) );

    $consumer = $this->getMock( $this->_target, [ 'publishRequest' ] );

    $consumer->expects( $this->once() )
      ->method( 'publishRequest' );

    $consumer->run( $event, 1 );

  } // runNoResponse

  /**
   * @param Behance\FireClient\Consumers\ResponseConsumer $consumer
   * @param GuzzleHttp\Subscriber\Mock $mock
   *
   * @return GuzzleHttp\Client
   */
  private function _buildClient( ResponseConsumer $consumer, Mock $mock ) {

    $client     = new Client();
    $emitter    = $client->getEmitter();
    $subscriber = new WildfireSubscriber();

    $subscriber->setConsumer( $consumer );

    $emitter->attach( $mock );
    $emitter->attach( $subscriber );

    return $client;

  } // _buildClient


  /**
   * @param string $type
   * @param string $body
   * @param array  $additional_descriptors
   *
   * @return string
   */
  private function _buildWildfireValue( $type, $body, array $additional_descriptors = [] ) {

    $descriptor = [
        'Type' => strtoupper( $type ),
        'File' => __FILE__,
        'Line' => __LINE__
    ];

    $descriptor = array_merge( $descriptor, $additional_descriptors );
    $response   = json_encode( [ $descriptor, $body ] );

    return (string)strlen( $response ) . '|' . $response . '|';

  } // _buildWildfireValue

} // ResponseConsumerTest
