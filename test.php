<?php
/**
 * Test File
 *
 * This is just a test file for the XMPP class to test it out.
 *
 * PHP Version 5
 *
 * @package   Tests
 * @author    Alex Mace <alex@hollytree.co.uk>
 * @copyright 2010 Alex Mace
 * @license   The PHP License http://www.php.net/license/
 */
require_once 'XMPP.php';
require_once 'XMPP/Message.php';

try {
	$xmpp = new XMPP(
		'phabbio@macefield.hollytree.co.uk', 'phabbio', '192.168.0.10'
	);
	$xmpp->connect();
	$xmpp->authenticate();
	$xmpp->bind();
	$xmpp->establishSession();
	$xmpp->presence();
	$xmpp->message('admin@macefield.hollytree.co.uk', 'Testing!');
	while(true) {
		$type = $xmpp->wait();

		if ($type == 'message') {
			$message = $xmpp->getMessage();
			var_dump($message->getBodies());
		}
	}
} catch (XMPP_Exception $e) {
	echo $e->getMessage() . "\n";
}
