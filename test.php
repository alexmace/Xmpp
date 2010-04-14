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

// Add Zend Framework onto the include path
ini_set(
	'include_path',
	'/Users/alex/Sites/zf/library' . PATH_SEPARATOR . ini_get('include_path'));
date_default_timezone_set('Europe/London');

require_once 'XMPP/Connection.php';
require_once 'XMPP/Message.php';

try {
	$xmpp = new Xmpp_Connection(
		'phabbio@macefield.hollytree.co.uk', 'phabbio', '192.168.0.10'
	);
	$xmpp->connect();
	$xmpp->authenticate();
	$xmpp->bind();
	$xmpp->establishSession();
	$xmpp->presence();
	if ($xmpp->isMucSupported()) {
		$xmpp->join('testing@conference.macefield.hollytree.co.uk', 'alex');
		$xmpp->message('testing@conference.macefield.hollytree.co.uk', 'Hello room');
	}
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
/*
<?xml version="1.0"?>
<iq type="result" id="disco1" from="macefield.hollytree.co.uk" to="phabbio@macefield.hollytree.co.uk/NewXmpp">
	<query xmlns="http://jabber.org/protocol/disco#info">
		<identity category="server" name="Openfire Server" type="im"/>
		<identity category="pubsub" type="pep"/>
		<feature var="http://jabber.org/protocol/pubsub#manage-subscriptions"/>
		<feature var="http://jabber.org/protocol/pubsub#modify-affiliations"/>
		<feature var="http://jabber.org/protocol/pubsub#retrieve-default"/>
		<feature var="http://jabber.org/protocol/pubsub#collections"/>
		<feature var="jabber:iq:private"/>
		<feature var="http://jabber.org/protocol/disco#items"/>
		<feature var="vcard-temp"/>
		<feature var="http://jabber.org/protocol/pubsub#publish"/>
		<feature var="http://jabber.org/protocol/pubsub#subscribe"/>
		<feature var="http://jabber.org/protocol/pubsub#retract-items"/>
		<feature var="http://jabber.org/protocol/offline"/>
		<feature var="http://jabber.org/protocol/pubsub#meta-data"/>
		<feature var="jabber:iq:register"/>
		<feature var="http://jabber.org/protocol/pubsub#retrieve-subscriptions"/>
		<feature var="http://jabber.org/protocol/pubsub#default_access_model_open"/>
		<feature var="jabber:iq:roster"/>
		<feature var="http://jabber.org/protocol/pubsub#config-node"/>
		<feature var="http://jabber.org/protocol/address"/>
		<feature var="http://jabber.org/protocol/pubsub#publisher-affiliation"/>
		<feature var="http://jabber.org/protocol/pubsub#item-ids"/>
		<feature var="http://jabber.org/protocol/pubsub#instant-nodes"/>
		<feature var="http://jabber.org/protocol/commands"/>
		<feature var="http://jabber.org/protocol/pubsub#multi-subscribe"/>
		<feature var="http://jabber.org/protocol/pubsub#outcast-affiliation"/>
		<feature var="http://jabber.org/protocol/pubsub#get-pending"/>
		<feature var="google:jingleinfo"/>
		<feature var="jabber:iq:privacy"/>
		<feature var="http://jabber.org/protocol/pubsub#subscription-options"/>
		<feature var="jabber:iq:last"/>
		<feature var="http://jabber.org/protocol/pubsub#create-and-configure"/>
		<feature var="urn:xmpp:ping"/>
		<feature var="http://jabber.org/protocol/pubsub#retrieve-items"/>
		<feature var="jabber:iq:time"/>
		<feature var="http://jabber.org/protocol/pubsub#create-nodes"/>
		<feature var="http://jabber.org/protocol/pubsub#persistent-items"/>
		<feature var="jabber:iq:version"/>
		<feature var="http://jabber.org/protocol/pubsub#presence-notifications"/>
		<feature var="http://jabber.org/protocol/pubsub"/>
		<feature var="http://jabber.org/protocol/pubsub#retrieve-affiliations"/>
		<feature var="http://jabber.org/protocol/pubsub#delete-nodes"/>
		<feature var="http://jabber.org/protocol/pubsub#purge-nodes"/>
		<feature var="http://jabber.org/protocol/disco#info"/>
		<feature var="http://jabber.org/protocol/rsm"/>
	</query>
</iq>*/
/*
<iq type="result" id="disco1" from="conference.macefield.hollytree.co.uk" to="phabbio@macefield.hollytree.co.uk/NewXmpp">
	<query xmlns="http://jabber.org/protocol/disco#info">
		<identity category="conference" name="Public Chatrooms" type="text"/>
		<identity category="directory" name="Public Chatroom Search" type="chatroom"/>
		<feature var="http://jabber.org/protocol/muc"/><feature var="http://jabber.org/protocol/disco#info"/><feature var="http://jabber.org/protocol/disco#items"/><feature var="jabber:iq:search"/><feature var="http://jabber.org/protocol/rsm"/></query></iq>*/