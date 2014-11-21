<?php

namespace Behance\FireClient\Subscribers;

use Behance\FireClient\Subscribers\WildfireSubscriber;
use Behance\FireClient\Consumers\ResponseConsumer;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Exception\RequestException;

use FirePHP as FirePHP;

class ResponseConsumerTest extends \PHPUnit_Framework_TestCase {

  private $_target = 'Behance\\FireClient\\Consumers\\ResponseConsumer';

  /**
   * @test
   * @dataProvider nonWildfireHeaderProvider
   */
  public function runNonWildfire( $header ) {

    $response = new Response( 200, $header );
    $consumer = $this->getMock( $this->_target, [ '_proxy' ] );

    $consumer->expects( $this->never() )
      ->method( '_proxy' );

    $consumer->run( $response );

  } // runNonWildfire


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
  public function runWildfire( $header, $type, $expected ) {

    $prefix = '[NEW PREFIX]';
    $client = $this->getMock( 'FirePHP', [ $type ] );

    $client->expects( $this->once() )
      ->method( $type )
      ->with( $prefix . ' ' . $expected );

    $response = new Response( 200, $header );
    $consumer = $this->getMock( $this->_target, null, [ $client ] );

    $consumer->setRemotePrefix( $prefix );
    $consumer->run( $response );

  } // runWildfire


  /**
   * Same test as below, but captures request at client level instead of during reporting
   * @test
   */
  public function runWildfireErrorWarn() {

    $prefix  = '[PREFIX]';
    $client  = $this->getMock( 'FirePHP', [ 'warn' ] );
    $client->expects( $this->once() )
      ->method( 'warn' )
      ->with( $this->stringStartsWith( $prefix . ' ' ) );

    $headers = [ ResponseConsumer::PROTOCOL_PREFIX . '1-2-3-4' => 'abcdefg' ];

    $response = new Response( 200, $headers );
    $consumer = $this->getMock( $this->_target, null, [ $client ] );

    $consumer->setRemotePrefix( $prefix );
    $consumer->run( $response );

  } // runWildfireErrorWarn


  /**
   * @test
   * @dataProvider wildfireErrorPayloadProvider
   */
  public function runWildfireError( $payload, $expected_error ) {

    $client  = $this->getMock( 'FirePHP' );
    $headers = [ ResponseConsumer::PROTOCOL_PREFIX . '1-2-3-4' => $payload ];

    $response = new Response( 200, $headers );
    $consumer = $this->getMock( $this->_target, [ '_reportParseError' ], [ $client ] );

    $consumer->expects( $this->once() )
      ->method( '_reportParseError' )
      ->with( $expected_error );

    $consumer->run( $response );

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
    $prefix  = ResponseConsumer::PROTOCOL_PREFIX;
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
   * @param string $type
   * @param string $body
   *
   * @return string
   */
  private function _buildWildfireValue( $type, $body ) {

    $descriptor = [
        'Type' => strtoupper( $type ),
        'File' => __FILE__,
        'Line' => __LINE__
    ];

    $response = json_encode( [ $descriptor, $body ] );

    return (string)strlen( $response ) . '|' . $response . '|';

  } // _buildWildfireValue

} // ResponseConsumerTest
