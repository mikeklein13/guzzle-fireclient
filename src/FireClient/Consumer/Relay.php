<?php

class FireClient_Consumer_Relay {

  const PROTOCOL_PREFIX = 'X-Wf-';


  public function setConsole( FirePHP $firephp ) {

    $this->_console = $firephp;

  } // setConsole

  public function getConsole() {

    return $this->_console;

  } // console

  /**
   * @return array  re-formatted messages
   */
  public function parse( array $messages ) {

    // Preprocess this header list, determine if there are ANY logs to display at all
    $protocol = self::PROTOCOL_PREFIX;

    foreach ( $messages as $line ) {

      if ( !$this->wfMessageExists( $line ) {
        continue;
      }

      $line    = preg_replace( "/{$protocol}(\d+)-(\d+)-(\d+)-(\d+)\: (\d+)/", '', $line );
      $line    = trim( $line );
      $line    = trim( trim( $line, '|' ) );
      $decoded = json_decode( $line, true );

      // TODO: Do something about this...
      if ( $decoded === null ) {
        $this->getConsole()->warning( "Unable to read line: {$line}" );
        continue;
      }

      $output = $decoded[1];

      if ( is_array( $output ) && $decoded[0]['Type'] == FirePHP::TABLE ) {

        $table   = [];

        foreach ( $output as $key ) {
          $table[]= $key;
        }

        $label = false;

        if ( !isset( $decoded[0]['Label'] ) ) {
          $this->getConsole()->warning( "Unable to read FirePHP header Label: " . var_export( $decoded, 1 ) );
          continue;
        }

        $label = $decoded[0]['Label'];
        $this->getConsole()->table( 'table', "<<REMOTE>> {$label}", $table );

        continue;

      } // if is_array

      // Since we cannot gain access to output trace method directly (it is created as requested), recreate it as a table
      if ( is_array( $output ) && $decoded[0]['Type'] == FirePHP::TRACE ) {

        $table   = [];
        $table[] = [ 'File', 'Line', 'Instruction' ];

        foreach ( $decoded[1]['Trace'] as $trace ) {

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

          $args = implode( ', ', $args );

          $instruction = $trace['class'] . $trace['type'] . $trace['function'] . "({$args})";

          $table[]= [
              $file,
              $line,
              $instruction,
          ];

        } // foreach trace

        $label = '(Trace) ' . ( ( empty( $decoded[1]['Message'] ) )
                              ? ''
                              : $decoded[1]['Message'] );

        $this->getConsole()->table( "<<REMOTE>> {$label}", $table );

        continue;

      } // if


      $output = $decoded[1];

      if ( is_array( $output ) ) {
        $output = var_export( $output, 1 );
      }

      switch ( $decoded[0]['Type'] ) {

        case 'WARN':
          $this->getConsole()->warning( "<<REMOTE>> {$output}" );
          break;

        case 'LOG':
        case 'INFO':
          $this->getConsole()->info( "<<REMOTE>> {$output}" );
          break;

        case 'ERROR':
          $this->getConsole()->error( "<<REMOTE>> {$output}" );
          break;

        default:
          throw new Core_Exception( "Unhandled Console type: " . var_export( $decoded, 1 ), Core_Codes::ERROR_NOT_SUPPORTED );

      } // switch type

    } // foreach header

  } // parse


  /**
   * @return bool
   */
  public function wfMessageExists( $line ) {

    $protocol = self::PROTOCOL_PREFIX;

    return ( preg_match( "/{$protocol}/", $line ) === 0 );

  } // wfMessageExists

} // FireClient_Consumer_Relay