<?php
/**
 * PHP XMPP Library
 *
 * PHP version 5
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled with this 
 * package in the file LICENSE.
 * It is also available through the world-wide-web at this URL: 
 * http://m.me.uk/license
 *
 * @category  XMPP
 * @package   XMPP
 * @author    Alex Mace <a@m.me.uk>
 * @copyright 2010-2011 Alex Mace (http://m.me.uk)
 * @license   http://m.me.uk/license New BSD License
 * @link      http://m.me.uk/xmpp
 */

// Add Zend Framework onto the include path
ini_set(
    'include_path', 
    '/Users/amace/Sites/zendframework/library' . PATH_SEPARATOR . ini_get('include_path')
);
date_default_timezone_set('Europe/London');

require_once 'Xmpp/Connection.php';
require_once 'Xmpp/Stanza.php';
require_once 'Xmpp/Message.php';

try {
    $xmpp = new Xmpp_Connection(
                    'phergie@bacon.server.dev', 'gregregregragr', 'bacon.server.dev'
    );
    $xmpp->connect();
    $xmpp->authenticate();
    $xmpp->bind();
    $xmpp->establishSession();
    $xmpp->presence();
    if ($xmpp->isMucSupported()) {
        $xmpp->join('devteam@conference.bacon.server.dev', 'alex', true);
        $xmpp->message('devteam@conference.bacon.server.dev', 'Hello room');
    }
    while (true) {
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