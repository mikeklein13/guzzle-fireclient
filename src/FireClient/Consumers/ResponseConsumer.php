<?php

namespace FireClient\Consumers;

use GuzzleHttp\Message\Response;
use FirePHP as FirePHP;

/**
 * Attempts to proxy any Wildfire protocol message back over local client
 */
class ResponseConsumer {

  const PROTOCOL_PREFIX        = 'X-Wf-';

  const ERROR_BAD_SIZE         = 'Body does not match expected size, expected %expected_size% vs actual %actual_size%';
  const ERROR_UNABLE_TO_DECODE = 'Unable to decode line payload: %line%';
  const ERROR_WRONG_DATA_COUNT = "Line does not contain expected 2 components: %line%";
  const ERROR_MISSING_TYPE     = "Line descriptor does not contain type: %line%";
  const ERROR_TABLE_WRONG_DATA = "Table requires an array: %message%";
  const ERROR_UNHANDLED_TYPE   = "Unhandled message type: %message_type%";

  /**
   * @var string  when rebroadcasting message, how to prefix transmission
   */
  protected $_remote_prefix = '[REMOTE]';


  /**
   * @param FirePHP $client
   */
  public function __construct( FirePHP $client = null ) {

    $this->_client = ( $client ) ?: new FirePHP();

  } // __construct


  /**
   * @param string $prefix
   */
  public function setRemotePrefix( $prefix ) {

    $this->_remote_prefix = $prefix;

  } // setRemotePrefix


  /**
   * @param GuzzleHttp\Message\Response
   */
  public function run( Response $response ) {

    $headers = $response->getHeaders();

    foreach ( $headers as $header_name => $header_values ) {

      $header_values = ( is_array( $header_values ) )
                       ? $header_values
                       : (array)$header_values;

      foreach ( $header_values as $header_value ) {

        // Only attempt proxying when WF protocol is detected
        if ( $this->isLineWildfireProtocol( $header_name ) ) {
          $this->_proxy( $header_value );
        }

      } // foreach header_values

    } // foreach headers

  } // run


  /**
   * @param string $message
   *
   * @return bool
   */
  public function isLineWildfireProtocol( $message ) {

    return ( strpos( $message, self::PROTOCOL_PREFIX ) !== false );

  } // isLineWildfireProtocol


  /**
   * @param string $template
   * @param array  $context   replacements for $template
   *
   * @return string
   */
  protected function _reportParseError( $template, array $context = [] ) {

    $boundary = '%';

    foreach ( $context as $key => $value ) {

      $replacement = $boundary . $key . $boundary;
      $template    = str_replace( $replacement, $value, $template );

    } // foreach context

    $this->_client->warn( $this->_remote_prefix . ' ' . $template );

  } // _reportParseError


  /**
   * Rebroadcasts any wildfire protocol-enabled $line over client
   *
   * @param string $line   a single line from response headers
   */
  private function _proxy( $line ) {

    $client   = $this->_client;
    $prefix   = $this->_remote_prefix;

    $split         = strpos( $line, '|' );
    $expected_size = (int)substr( $line, 0, $split );
    $body          = trim( substr( $line, $split ), '|' );
    $actual_size   = strlen( $body );

    if ( $actual_size !== $expected_size ) {
      $this->_reportParseError( self::ERROR_BAD_SIZE, [ 'actual_size' => $actual_size, 'expected_size' => $expected_size ] );
      return;
    }

    $decoded = @json_decode( $body, true );

    // TODO: Do something about this...
    if ( $decoded === null ) {
      $this->_reportParseError( self::ERROR_UNABLE_TO_DECODE, [ 'line' => $line ] );
      return;
    }

    if ( count( $decoded ) !== 2 ) {
      $this->_reportParseError( self::ERROR_WRONG_DATA_COUNT, [ 'line' => $line ] );
      return;
    }

    $descriptor = $decoded[0];
    $message    = $decoded[1];

    if ( !isset( $descriptor['Type'] ) ) {
      $this->_reportParseError( self::ERROR_MISSING_TYPE, [ 'line' => $line ] );
      return;
    }

    $message_type = $descriptor['Type'];

    switch ( $message_type ) {

      case FirePHP::TABLE:
        $this->_proxyTable( $descriptor, $message );
        break;

      case FirePHP::TRACE:
        $this->_proxyTrace( $descriptor, $message );
        break;

      case FirePHP::WARN:
      case FirePHP::LOG:
      case FirePHP::INFO:
      case FirePHP::ERROR:
        $method = strtolower( $message_type );

        if ( is_array( $message ) ) {
          $message = var_export( $message, 1 );
        }

        $client->{$method}( "{$prefix} {$message}" );
        break;

      default:
        $this->_reportParseError( self::ERROR_UNHANDLED_TYPE, [ 'message_type' => $message_type ] );
        break;

    } // switch message_type

  } // _proxy


  /**
   * @param array        $descriptor
   * @param string|array $message
   */
  private function _proxyTable( array $descriptor, $message ) {

    $client = $this->_client;
    $prefix = $this->_remote_prefix;

    if ( !is_array( $message ) ) {
      $this->_reportParseError( self::ERROR_TABLE_WRONG_DATA, [ 'message' => var_export( $message, 1 ) ] );
      return;
    }

    $table = [];

    foreach ( $message as $key ) {
      $table[] = $key;
    }

    $label = false;

    if ( !isset( $descriptor['Label'] ) ) {
      $client->warn( "{$prefix} Unable to read descriptor label: " . var_export( $descriptor, 1 ) );
      return;
    }

    $label = $descriptor['Label'];
    $client->table( 'table', "{$prefix} {$label}", $table );

  } // _proxyTable


  /**
   * @param array        $descriptor
   * @param string|array $message
   */
  private function _proxyTrace( array $descriptor, $message ) {

    $descriptor; // Appease PHPMD

    $client = $this->_client;
    $prefix = $this->_remote_prefix;

    // Since we cannot gain access to message trace method directly (it is created as requested), recreate it as a table
    if ( !is_array( $message ) ) {
      $this->_reportParseError( self::ERROR_TABLE_WRONG_DATA, [ 'message' => var_export( $message, 1 ) ] );
      return;
    }

    $table   = [];
    $table[] = [ 'File', 'Line', 'Instruction' ];

    foreach ( $message['Trace'] as $trace ) {

      $file = ( empty( $trace['file'] ) )
              ? ''
              : $trace['file'];

      $line = ( empty( $trace['line'] ) )
              ? ''
              : $trace['line'];

      $trace['args'] = ( empty( $trace['args'] ) )
                       ? []
                       : $trace['args'];

      // Prevent array to string conversions by doing it explicitly
      $args = [];

      foreach ( $trace['args'] as $arg ) {

        $args[] = ( is_array( $arg ) )
                  ? 'Array'
                  : $arg;

      } // foreach args

      $args        = implode( ', ', $args );
      $instruction = $trace['class'] . $trace['type'] . $trace['function'] . "({$args})";
      $table[]     = [ $file, $line, $instruction ];

    } // foreach trace

    $label = '(Trace) ' . ( ( empty( $decoded[1]['Message'] ) )
                          ? ''
                          : $decoded[1]['Message'] );

    $client->table( "{$prefix} {$label}", $table );

  } // _proxyTrace

} // ResponseConsumer
