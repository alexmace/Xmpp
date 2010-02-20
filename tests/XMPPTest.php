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
require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../Stream.php';
require_once dirname(__FILE__).'/../XMPP.php';

/**
 * Tests for the XMPP class. Each tests need to mock the Stream object and stub
 * the _getStream method so that the XMPP class doesn't actually have to connect
 * to anything.
 *
 * @package Tests
 * @author  Alex Mace <alex@hollytree.co.uk>
 */
class XMPPTest extends PHPUnit_Framework_TestCase
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

	/**
	 * Test for the connect function
	 *
	 * @return void
	 */
	public function testConnect()
	{

		// Get a mock for the Stream class
		$stream = $this->getMock('Stream', array(), array(), '', false);

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

		// Now get a mock of XMPP and replace the _getStream function with a
		// stub that will return our stream mock.
		$xmpp = $this->getMock(
			'XMPP', array('_getStream'), //'_getStream'),
			array('test@test.server.com', 'testPass', 'test.xmpp.com'));
		$xmpp->expects($this->once())
			   ->method('_getStream')
			   ->with($this->equalTo('tcp://test.xmpp.com:5222'))
			   ->will($this->returnValue($stream));
		$xmpp->connect();

	}

	/**
	 * Destructor Test Case
	 *
	 * @todo Implement test__destruct().
	 *
	 * @return void
	 */
	public function testDestructor()
	{
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.');
	}
}
