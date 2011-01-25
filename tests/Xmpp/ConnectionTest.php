<?php
/**
 * XMPPTest
 *
 * Test case for the XMPP class.
 *
 * PHP Version 5
 *
 * @package   Tests
 * @author    Alex Mace <alex@hollytree.co.uk>
 * @copyright 2010 Alex Mace
 * @license   The PHP License http://www.php.net/license/
 */

require_once 'Stream.php';
require_once 'Xmpp/Connection.php';

/**
 * Tests for the XMPP class. Each tests need to mock the Stream object and stub
 * the _getStream method so that the XMPP class doesn't actually have to connect
 * to anything.
 *
 * @package Tests
 * @author  Alex Mace <alex@hollytree.co.uk>
 */
class Xmpp_ConnectionTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @return void
	 */
	public function setUp()
	{
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @return void
	 */
	public function tearDown()
	{
	}

	public function getMockStream()
	{
		return $this->getMock('Stream', array(), array(), '', false);
	}

	public function getMockXmppConnection(Stream $stream)
	{
		$xmpp = $this->getMock(
			'Xmpp_Connection', array('_getStream'),
			array('test@test.server.com', 'testPass', 'test.xmpp.com')
		);
		$xmpp->expects($this->once())
			   ->method('_getStream')
			   ->with($this->equalTo('tcp://test.xmpp.com:5222'))
			   ->will($this->returnValue($stream));

		return $xmpp;
	}

	/**
	 * Test for the connect function
	 *
	 * @return void
	 */
	public function testConnect()
	{
		$stream = $this->getMockStream();

		// Set up what we expect the XMPP class to send to the server
		$message = '<stream:stream to="test.xmpp.com" '
				 . 'xmlns:stream="http://etherx.jabber.org/streams" '
				 . 'xmlns="jabber:client" version="1.0">';
		$stream->expects($this->at(1))
			   ->method('send')
			   ->with($this->equalTo($message));

		// Next we expect to tell the stream to wait for a response from the
		// server
		$stream->expects($this->at(2))
			   ->method('select')
			   ->will($this->returnValue(1));

		// Set up what expect the reponse of the server to be to this
		$message = "<?xml version='1.0' encoding='UTF-8'?>"
				 . '<stream:stream '
				 . 'xmlns:stream="http://etherx.jabber.org/streams" '
				 . 'xmlns="jabber:client" from="test.xmpp.com" '
				 . 'id="e674e243" xml:lang="en" version="1.0">';
		$stream->expects($this->at(3))
			   ->method('read')
			   ->with($this->equalTo(4096))
			   ->will($this->returnValue($message));

		// Next we expect to tell the stream to wait for a response from the
		// server
		$stream->expects($this->at(4))
			   ->method('select')
			   ->will($this->returnValue(1));

		// Next the server should report what features it supports
		$message = '<stream:features>'
				 . '<starttls xmlns="urn:ietf:params:xml:ns:xmpp-tls"></starttls>'
				 . '<mechanisms xmlns="urn:ietf:params:xml:ns:xmpp-sasl">'
				 .     '<mechanism>DIGEST-MD5</mechanism>'
				 .     '<mechanism>PLAIN</mechanism>'
				 .     '<mechanism>ANONYMOUS</mechanism>'
				 .     '<mechanism>CRAM-MD5</mechanism>'
				 . '</mechanisms>'
				 . '<compression xmlns="http://jabber.org/features/compress">'
				 .     '<method>zlib</method>'
				 . '</compression>'
				 . '<auth xmlns="http://jabber.org/features/iq-auth"/>'
				 . '<register xmlns="http://jabber.org/features/iq-register"/>'
				 . '</stream:features>';
		$stream->expects($this->at(5))
			   ->method('read')
			   ->with($this->equalTo(4096))
			   ->will($this->returnValue($message));

		// Now get a mock of XMPP and replace the _getStream function with a
		// stub that will return our stream mock.
		$xmpp = $this->getMockXmppConnection($stream);
		$xmpp->connect();

	}

	public function testDisconnect()
	{
		$stream = $this->getMockStream();

		// Setup what we expect the Xmpp_Connection class to send to the server.
		// Disconnecting from the server should basically just be sending a
		// closing stream tag followed by disconnection.
		$message = '</stream:stream>';
		$stream->expects($this->at(0))
				->method('send')
				->with($this->equalTo($message));

		// Next we expect the Stream to be told to close to the connect
		$stream->expects($this->at(1))
			   ->method('disconnect')
			   ->will($this->returnValue(true));

		$xmpp = $this->getMockXmppConnection($stream);
		$xmpp->disconnect();
	}

}
