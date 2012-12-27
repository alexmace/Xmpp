<?php

/**
 * Stream
 *
 * PHP version 5
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://m.me.uk/xmpp/license/
 *
 * @category  Xmpp
 * @package   Xmpp
 * @author    Alex Mace <a@m.me.uk>
 * @copyright 2010-2011 Alex Mace (http://m.me.uk)
 * @license   http://m.me.uk/xmpp/license/ New BSD License
 * @link      http://pear.m.me.uk/package/Xmpp
 */

/**
 * Stream
 *
 * Stream is a wrapper for the PHP steam functions
 *
 * PHP Version 5
 *
 *
 * @package   Stream
 * @author    Alex Mace <alex@hollytree.co.uk>
 * @copyright 2010 Alex Mace
 * @license   The PHP License http://www.php.net/license/
 */

/**
 * The Stream class wraps up the stream functions, so you can pass around the
 * stream as an object and perform operations on it.
 *
 * @category Xmpp
 * @package  Xmpp
 * @author   Alex Mace <a@m.me.uk>
 * @license  http://m.me.uk/xmpp/license/ New BSD License
 * @link     http://pear.m.me.uk/package/Xmpp

 */
class Stream
{

    private $_conn = null;
    private $_connected = false;
    private $_errorNumber = 0;
    private $_errorString = '';
    private $_logger = null;

    /**
     * Creates an instance of the Stream class
     *
     * @param string   $remoteSocket The remote socket to connect to
     * @param int      $timeOut      Give up trying to connect after these
     *                               number of seconds
     * @param int      $flags        Connection flags
     * @param resource $context      Context of the stream
     */
    public function __construct(
        $remoteSocket, $timeOut = null, $flags = null, $context = null
    ) {

        // Attempt to set up logging
        $this->_logger = $this->_getLogger(Zend_Log::EMERG);

        // Attempt to make the connection. stream_socket_client needs to be
        // called in the correct way based on what we have been passed.
        if (is_null($timeOut) && is_null($flags) && is_null($context)) {
            $this->_conn = stream_socket_client(
                $remoteSocket, $this->_errorNumber, $this->_errorString
            );
        } else if (is_null($flags) && is_null($context)) {
            $this->_conn = stream_socket_client(
                $remoteSocket, $this->_errorNumber, $this->_errorString, $timeOut
            );
        } else if (is_null($context)) {
            $this->_conn = stream_socket_client(
                $remoteSocket, $this->_errorNumber, $this->_errorString, $timeOut,
                $flags
            );
        } else {
            $this->_conn = stream_socket_client(
                $remoteSocket, $this->_errorNumber, $this->_errorString, $timeOut,
                $flags, $context
            );
        }

        // If the connection comes back as false, it could not be established.
        // Note that a connection may appear to be successful at this stage and
        // yet be invalid. e.g. UDP connections are "connectionless" and not
        // actually made until they are required.
        if ($this->_conn === false) {
            throw new Stream_Exception($this->_errorString, $this->_errorNumber);
        }

        // Set the time out of the stream.
        stream_set_timeout($this->_conn, 1);

        $this->_connected = true;
    }

    /**
     * Class destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Closes the connection to whatever this class is connected to.
     *
     * @return void
     */
    public function disconnect()
    {
        // If there is a valid connection it will attempt to close it.
        if (!is_null($this->_conn) && $this->_conn !== false) {
            fclose($this->_conn);
            $this->_conn = null;
            $this->_connected = false;
        }
    }

    /**
     * Set up the instance of Zend_Log for doing logging.
     *
     * @param int $logLevel The level of logging to be done
     *
     * @return Zend_Log
     */
    private function _getLogger($logLevel)
    {
        $writer = new Zend_Log_Writer_Stream('php://output');
        return new Zend_Log($writer);
    }

    /**
     * Returns whether or not this object is currently connected to anything.
     *
     * @return booleans
     */
    public function isConnected()
    {
        return $this->_connected;
    }

    /**
     * Attempts to read some data from the stream
     *
     * @param int $length The amount of data to be returned
     *
     * @return string
     */
    public function read($length)
    {
        return fread($this->_conn, $length);
    }

    /**
     * Waits for some data to be sent or received on the stream
     *
     * @return int|boolean
     */
    public function select()
    {
        $read = array($this->_conn);
        $write = array();
        $except = array();

        return stream_select($read, $write, $except, 0, 200000);
    }

    /**
     * Will sent the message passed in down the stream
     *
     * @param string $message Content to be sent down the stream
     *
     * @return void
     */
    public function send($message)
    {
        // Perhaps need to check the stream is still open here?
        $this->_logger->debug('Sent: ' . $message);

        // Write out the message to the stream
        return fwrite($this->_conn, $message);
    }

    /**
     * Turns blocking on or off on the stream
     *
     * @param boolean $enable Set what to do with blocking, turn it on or off.
     *
     * @return boolean
     */
    public function setBlocking($enable)
    {
        if ($enable) {
            $res = stream_set_blocking($this->_conn, 1);
        } else {
            $res = stream_set_blocking($this->_conn, 0);
        }

        return $res;
    }

    /**
     * Toggle whether or not TLS is use on the connection.
     *
     * @param boolean $enable Whether or not to turn on TLS.
     *
     * @return mixed
     */
    public function setTLS($enable)
    {
        if ($enable) {
            $res = stream_socket_enable_crypto(
                $this->_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
            );
        } else {
            $res = stream_socket_enable_crypto(
                $this->_conn, false, STREAM_CRYPTO_METHOD_TLS_CLIENT
            );
        }

        return $res;
    }

}