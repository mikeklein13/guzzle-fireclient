<?php

namespace FireClient;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Guzzle\Common\Event;

class FirePHPSubscriber implements EventSubscriberInterface {

  protected $_start_time_ms;

  public static function getSubscribedEvents() {

    return [
        'request.before_send' => 'onBeforeSend',
        'request.complete'    => 'onComplete'
    ];


  } // getSubscribedEvents


  public function onBeforeSend( Event $event ) {

    $this->_start_time_ms = microtime( true );

    $request = $event['request'];

    $request->addHeader( 'User-Agent', 'FirePHP/0.7.4' );

  } // onBeforeSend


  public function onComplete( Event $event ) {

    $total    = microtime( true ) - $this->_start_time_ms;
    $response = $event['response'];

    var_dump( $response->getHeader( 'X-Wf-Protocol-1' ) );
    die;
    // Retrieve headers

  } // onComplete

  /**
   * @param Object $console      : logging object (typically FirePHP)
   * @param float  $time_sec     : length of the request, in fractions of a second
   * @param string $request_body : what was sent
   */
  public function consoleLog( $console, $time_sec, $request_body = '', $options = array() ) {

    $max_length = 500;

    $multi   = ( !empty( $options['multi'] ) );

    $table   = array();
    $table[] = array( 'Key', 'Value' );
    $table[] = array( 'Method', $this->_requestMethod );

    if ( isset( $this->_requestParams[ CURLOPT_HTTPHEADER ] ) )
      $table[] = array( 'Header', implode( ', ', $this->_requestParams[ CURLOPT_HTTPHEADER ] ) );

    if ( is_array( $request_body ) )
      $request_body = json_encode( $request_body );

    $request_body = ( strlen( $request_body ) > $max_length )
                    ? substr( $request_body, 0, $max_length ) . '...'
                    : $request_body;

    $response_body = ( strlen( $this->_body ) > $max_length )
                     ? substr( $this->_body, 0, $max_length ) . '...'
                     : $this->_body;


    $table[] = array( 'Url',      $this->_requestUrl );
    $table[] = array( 'Request',  $request_body );
    $table[] = array( 'Response', $response_body );

    if ( $this->_httpCode == Core_Curl::HTTP_FOUND ) {

      $headers  = $this->_stringHeaderToArray( $this->_header );

      // check to see if Location is a field in the response header
      $location = $this->_getResponseField( 'Location', $headers );
      $table[]  = array( 'Location', $location );

    } // if httpCode = found

    $time_sec = round( $time_sec, 4 ) . 's';
    $filler   = 8 - strlen( $time_sec );

    for ( $ix = 0; $ix < $filler; ++$ix )
      $time_sec .= '.';

    $error_message_text = '';

    if ( $this->_error )
      $error_message_text = " [{$this->_error}]";

    $curl = ( $multi )
            ? "MULTICURL"
            : "CURL";

    $console->table( "<<REMOTE:{$curl}>> ({$time_sec}): [{$this->_requestMethod} - {$this->_httpCode}] {$this->_requestUrl}{$error_message_text}" , $table );


    return $this->_logHeaders( $console, $this->_header, $multi );


  } // consoleLog


  /**
   * TODO: this will be $console-specific, needs to be moved into console object itself
   *
   * @param Object $console
   * @param string $header_string
   */
  protected function _logHeaders( $console, $header, $multi = false ) {

    $header  = $this->_stringHeaderToArray( $header );

    $in_use  = false;
    $curl    = ( $multi )
               ? "MULTICURL"
               : "CURL";

    // Preprocess this header list, determine if there are ANY logs to display at all
    // If so, group them
    foreach ( $header as $line ) {

      if ( preg_match( "/X-Wf-\d-\d/", $line ) === 0 ) {

        $in_use = true;
        break;

      } // if preg_match

    } // foreach header

    //if ( $in_use )
      //$console->group( "Remote Console: {$this->_requestUrl}", array( 'Collapsed' => false ) );

    foreach ( $header as $line ) {

      if ( preg_match( "/X-Wf-\d-\d/", $line ) === 0 )
        continue;

      $line    = preg_replace( "/X-Wf-(\d+)-(\d+)-(\d+)-(\d+)\: (\d+)/", '', $line );
      $line    = trim( $line );
      $line    = trim( trim( $line, '|' ) );
      $decoded = json_decode( $line, true );

      // TODO: Do something about this...
      if ( $decoded === null ) {
        $console->warning( "Unable to read line: {$line}" );
        continue;
      }

      $output = $decoded[1];

      if ( is_array( $output ) && $decoded[0]['Type'] == 'TABLE' ) {

        $table   = array();

        foreach ( $output as $key )
          $table[]= $key;

        $label = false;

        if ( !isset( $decoded[0]['Label'] ) ) {
          $console->warning( "Unable to read FirePHP header Label: " . var_export( $decoded, 1 ) );
          continue;
        }

        $label = $decoded[0]['Label'];
        $console->table( "<<REMOTE>> {$label}", $table );

        continue;

      } // if is_array

      // Since we cannot gain access to output trace method directly (it is created as requested), recreate it as a table
      if ( is_array( $output ) && $decoded[0]['Type'] == 'TRACE' ) {

        $table   = array();
        $table[] = array( 'File', 'Line', 'Instruction' );

        foreach ( $decoded[1]['Trace'] as $trace ) {

          $file = ( empty( $trace['file'] ) )
                  ? ''
                  : $trace['file'];

          $line = ( empty( $trace['line'] ) )
                  ? ''
                  : $trace['line'];

          $trace['args'] = ( empty( $trace['args'] ) )
                           ? array()
                           : $trace['args'];

          // Prevent array to string conversions by doing it explicitly
          $args = array();
          foreach ( $trace['args'] as $arg ) {

            $args[] = ( is_array( $arg ) )
                      ? 'Array'
                      : $arg;

          } // foreach args

          $args = implode( ', ', $args );

          $instruction = $trace['class'] . $trace['type'] . $trace['function'] . "({$args})";

          $table[]= array(
              $file,
              $line,
              $instruction,
          );

        } // foreach trace

        $label = '(Trace) ' . ( ( empty( $decoded[1]['Message'] ) )
                              ? ''
                              : $decoded[1]['Message'] );

        $console->table( "<<REMOTE>> {$label}", $table );

        continue;

      } // if


      $output = $decoded[1];

      if ( is_array( $output ) ) {
        $output = var_export( $output, 1 );
      }

      switch ( $decoded[0]['Type'] ) {

        case 'WARN':
          $console->warning( "<<REMOTE>> {$output}" );
          break;

        case 'LOG':
        case 'INFO':
          $console->info( "<<REMOTE>> {$output}" );
          break;

        case 'ERROR':
          $console->error( "<<REMOTE>> {$output}" );
          break;

        default:
          throw new Core_Exception( "Unhandled Console type: " . var_export( $decoded, 1 ), Core_Codes::ERROR_NOT_SUPPORTED );

      } // switch type

    } // foreach header

  } // _logHeaders

} // FirePHPSubscriber