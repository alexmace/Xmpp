<?php
/**
 * Xmpp
 *
 * Xmpp is a implementation of the Xmpp protocol.
 *
 * PHP Version 5
 *
 * @package   Xmpp
 * @author    Alex Mace <alex@hollytree.co.uk>
 * @copyright 2010 Alex Mace
 * @license   The PHP License http://www.php.net/license/
 */
require_once 'Xmpp/Exception.php';
require_once 'Stream.php';
require_once 'Zend/Log.php';
require_once 'Zend/Log/Writer/Stream.php';

/**
 * Xmpp is an implementation of the Xmpp protocol. Note that creating the class
 * does not connect to the server specified in the constructor. You need to call
 * connect() to actually perform the connection.
 *
 * @package Xmpp
 * @author  Alex Mace <alex@hollytree.co.uk>
 * @todo Store what features are available when session is established
 * @todo Handle error conditions of authenticate, bind, connect and
 *		 establishSession
 * @todo Throw exceptions when attempting to perform actions that the server has
 *		 not reported that they support.
 * @todo ->wait() method should return a class that encapsulates what has come
 *		 from the server. e.g. Xmpp_Message Xmpp_Iq Xmpp_Presence
 */
class Xmpp_Connection
{

	const PRESENCE_AWAY = 'away';
	const PRESENCE_CHAT = 'chat';
	const PRESENCE_DND = 'dnd';
	const PRESENCE_XA = 'xa';

	/**
	 * Host name of the server to connect to
	 *
	 * @var string
	 */
	private $_host = null;

	/**
	 * List of items available on the server
	 *
	 * @var array
	 */
	protected $_items = null;

	/**
	 * Holds an array of rooms that have been joined on this connection.
	 *
	 * @var array
	 */
	protected $_joinedRooms = array();

	private $_lastResponse = null;

	/**
	 * Class that performs logging
	 *
	 * @var Zend_Log
	 */
	private $_logger = null;

	private $_mechanisms = array();

	/**
	 * Holds the password of the user we are going to connect with
	 *
	 * @var string
	 */
	private $_password = null;

	/**
	 * Holds the port of the server to connect to
	 *
	 * @var int
	 */
	private $_port = null;

	/**
	 * Holds the "realm" of the user name. Usually refers to the domain in the
	 * user name.
	 *
	 * @var string
	 */
	private $_realm = '';

	/**
	 * Holds the resource for the connection. Will be something like a machine
	 * name or a location to identify the connection.
	 *
	 * @var string
	 */
	private $_resource = '';

	/**
	 * Whether or not this connection to switch SSL when it is available.
	 * 
	 * @var boolean
	 */
	private $_ssl = true;

	/**
	 * Holds the Stream object that performs the actual connection to the server
	 *
	 * @var Stream
	 */
	private $_stream = null;

	/**
	 * Holds the username used for authentication with the server
	 *
	 * @var string
	 */
	private $_userName = null;

	/**
	 * Class constructor
	 *
	 * @param string $userName Username to authenticate with
	 * @param string $password Password to authenticate with
	 * @param string $host     Host name of the server to connect to
	 * @param string $ssl      Whether or not to connect over SSL if it is
	 *                         available.
	 * @param int    $logLevel Level of logging to be performed
	 * @param int    $port     Port to use for the connection
	 * @param string $resource Identifier of the connection
	 */
	public function __construct(
		$userName, $password, $host, $ssl = true, $logLevel = Zend_Log::EMERG,
		$port = 5222, $resource = 'NewXmpp'
	) {

		// First set up logging
		$this->_host = $host;
		$this->_logger = $this->_getLogger($logLevel);
		$this->_password = $password;
		$this->_port = $port;
		$this->_resource = $resource;
		$this->_ssl = $ssl;
		list($this->_userName, $this->_realm) = array_pad(explode('@', $userName), 2, null);

	}

	public function authenticate()
	{
		// Check that the server said that DIGEST-MD5 was available
		if ($this->_mechanismAvailable('DIGEST-MD5')) {

			// Send message to the server that we want to authenticate
			$message = "<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' "
					 . "mechanism='DIGEST-MD5'/>";
			$this->_logger->debug('Requesting Authentication: ' . $message);
			$this->_stream->send($message);

			// Wait for challenge to come back from the server
			$response = $this->_waitForServer('challenge');
			$this->_logger->debug('Response: ' . $response->asXML());

			// Decode the response
			$decodedResponse = base64_decode((string)$response);
			$this->_logger->debug(
				'Response (Decoded): ' . $decodedResponse);
			
			// Split up the parts of the challenge
			$challengeParts = explode(',', $decodedResponse);
			
			// Create an array to hold the challenge
			$challenge = array();

			// Process the parts and put them into the array for easy access
			foreach($challengeParts as $part) {
				list($key,$value) = explode('=', $part);
				$challenge[$key] = trim($value, '"');
			}

			$cnonce = uniqid();
			$a1 = pack('H32', md5($this->_userName . ':' . $challenge['realm']
				. ':' . $this->_password)) . ':' . $challenge['nonce'] . ':'
				. $cnonce;
			$a2 = 'AUTHENTICATE:xmpp/' . $challenge['realm'];
			$ha1 = md5($a1);
			$ha2 = md5($a2);
			$kd = $ha1 . ':' . $challenge['nonce'] . ':00000001:'
				. $cnonce . ':' . $challenge['qop'] . ':' . $ha2;
			$z = md5($kd);

			// Start constructing message to send with authentication details in
			// it.
			$message = 'username="' . $this->_userName . '",'
					 . 'realm="' . $challenge['realm'] . '",'
					 . 'nonce="' . $challenge['nonce'] . '",'
					 . 'cnonce="' . $cnonce . '",nc="00000001",'
					 . 'qop="' . $challenge['qop'] . '",'
					 . 'digest-uri="xmpp/' . $challenge['realm'] . '",'
					 . 'response="' . $z . '",'
					 . 'charset="' . $challenge['charset'] .'"';
			$this->_logger->debug('Unencoded Response: ' . $message);
			$message = "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>"
					 . base64_encode($message) . '</response>';

			// Send the response
			$this->_logger->debug('Challenge Response: ' . $message);
			$this->_stream->send($message);

			// Should get another challenge back from the server. Some servers
			// don't bother though and just send a success back with the
			// rspauth encoded in it.
			$response = $this->_waitForServer('*');
			$this->_logger->debug('Response: ' . $response->asXML());

			// If we have got a challenge, we need to send a response, blank
			// this time.
			if ($response->getName() == 'challenge') {
				$message = "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'/>";

				// Send the response
				$this->_logger->debug('Challenge Response: ' . $message);
				$this->_stream->send($message);

				// This time we should get a success message.
				$response = $this->_waitForServer('success');
				$this->_logger->debug('Response: ' . $response->asXML());

			}


			// Now that we have been authenticated, a new stream needs to be
			// started.
			$this->_startStream();

			// Server should now respond with start of stream and list of
			// features
			$response = $this->_waitForServer('stream:stream');
			$this->_logger->debug('Received: ' . $response);

			// If the server has not yet said what features it supports, wait
			// for that
			if (strpos($response->asXML(), 'stream:features') === false) {
				$response = $this->_waitForServer('stream:features');
				$this->_logger->debug('Received: ' . $response);
			}

		}

		return true;
	}

	public function bind()
	{
		// Need to bind the resource with the server
		$message = "<iq type='set' id='bind_2'>"
				 . "<bind xmlns='urn:ietf:params:xml:ns:xmpp-bind'>"
				 . '<resource>' . $this->_resource . '</resource>'
				 . '</bind></iq>';
		$this->_logger->debug('Bind request: ' . $message);
		$this->_stream->send($message);

		// Should get an iq response from the server confirming the jid
		$response = $this->_waitForServer('*');
		$this->_logger->debug('Response: ' . $response->asXML());

		return true;
	}

	/**
	 * Connects to the server and upgrades to TLS connection if possible
	 *
	 * @return void
	 */
	public function connect()
	{
		// Figure out where we need to connect to
		$server = $this->_getServer();

		try {

			// Get a connection to server
			$this->_stream = $this->_getStream($server);
			$this->_logger->debug('Connection made');

			// Set the stream to blocking mode
			$this->_stream->setBlocking(true);
			$this->_logger->debug('Blocking enabled');

			// Attempt to send the stream start
			$this->_startStream();

			$this->_logger->debug('Wait for response from server');

			// Now we will expect to get a stream tag back from the server. Not
			// sure if we're supposed to do anything with it, so we'll just drop
			// it for now. May contain the features the server supports.
			$response = $this->_waitForServer('stream:stream');
			$this->_logger->debug('Received: ' . $response);

			// If the response from the server does contain a features tag,
			// don't bother querying server to get it.
			// TODO - Xpath would probably be more sensible for this, but for
			// now this'll work.
			if (strpos($response->asXml(), '<stream:features') === false) {

				// Server should now send back a features tag telling us what
				// features it supports. If it tells us to start tls then we
				// will need to change to a secure connection. It will also tell
				// us what authentication methods it supports.
				//
				// Note we check for a "features" tag rather than
				// stream:features because it is namespaced.
				$response = $this->_waitForServer('features');
				$this->_logger->debug('Received: ' . $response);

			}

			// Set mechanisms based on that tag
			$this->_setMechanisms($response);

			// If there was a starttls tag in there, and this connection has SSL
			// enabled, then we should tell the server that we will start up tls
			// as well.
			if (strpos($response, '<starttls xmlns="urn:ietf:params:xml:ns:xmpp-tls"></starttls>') !== false
				&& $this->_ssl === true) {
				$this->_logger->debug('Informing server we will start TLS');
				$message = "<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'/>";
				$this->_stream->send($message);

				// Wait to get the proceed message back from the server
				$response = $this->_waitForServer('proceed');
				$this->_logger->debug('Received: ' . $response->asXML());

				// Once we have the proceed signal from the server, we should
				// turn on TLS on the stream and send the opening stream tag
				// again.
				$this->_stream->setTLS(true);
				$this->_logger->debug('Enabled TLS');

				// Now we need to start a new stream again.
				$this->_startStream();

				// Server should now respond with start of stream and list of
				// features
				$response = $this->_waitForServer('stream');
				$this->_logger->debug('Received: ' . $response);

				// Set mechanisms based on that tag
				$this->_setMechanisms($response);

			}

		} catch(Stream_Exception $e) {
			// A Stream Exception occured. Catch it and rethrow it as an Xmpp
			// Exception.
			throw new Xmpp_Exception('Failed to connect: ' . $e->getMessage());
		}

		return true;

	}

	public function disconnect()
	{
		$message = '</stream:stream>';

		// If the stream isn't set, get one. Seems unlikely that we'd want to be
		// disconnecting when no connection is open via a stream, but it saves
		// us having to go through the rigormoral of actually setting up a
		// proper, full mock connection.
		if (!isset($this->_stream)) {
			$this->_stream = $this->_getStream($this->_getServer());
		}

		$this->_stream->send($message);
		$this->_stream->disconnect();
		$this->_logger->debug('Disconnected');

		return true;
	}

	public function establishSession()
	{

		// Send message requesting start of session.
		$message = "<iq to='" . $this->_realm . "' type='set' id='sess_1'>"
				 . "<session xmlns='urn:ietf:params:xml:ns:xmpp-session'/>"
				 . "</iq>";
		$this->_stream->send($message);

		// Should now get an iq in response from the server to say the session
		// was established.
		$response = $this->_waitForServer('iq');
		$this->_logger->debug('Received: ' . $response->asXML());

		return true;

	}
	
	public function getIq()
	{
		if ((string)$this->_lastResponse->getName() != 'iq') {
			throw new Xmpp_Exception('Last stanza received was not an iq stanza');
		}

		return new Xmpp_Iq($this->_lastResponse);
		
	}

	public function getMessage()
	{
		if ((string)$this->_lastResponse->getName() != 'message') {
			throw new Xmpp_Exception('Last stanza received was not a message');
		}

		return new Xmpp_Message($this->_lastResponse);
	}

	/**
	 *
	 * @todo Allow multiple statuses to be entered
	 * @param <type> $status
	 * @param <type> $show
	 * @param <type> $priority
	 */
	public function presence($status = null, $show = null, $priority = null)
	{
		if (is_null($status) && is_null($show) && is_null($priority)) {
			$message = '<presence/>';
		} else {
			$message = "<presence xml:lang='en'>";

			if (!is_null($status)) {
				$message .= '<status>' . $status . '</status>';
			}

			if (!is_null($show) && ($show == self::PRESENCE_AWAY
				|| $show == self::PRESENCE_CHAT || $show == self::PRESENCE_DND
				|| $show == self::PRESENCE_XA)) {
				$message .= '<show>' . $show . '</show>';
			}

			if (!is_null($priority) && is_int($priority)) {
				$message .= '<priority>' . $priority . '</priority>';
			}

			$message .= '</presence>';
		}
		$this->_stream->send($message);

		return true;
	}

	/**
	 *
	 * @todo Get this to return after a timeout period if nothing has come back
	 * @return string
	 */
	public function wait() {

		// Wait for any tag to be sent by the server
		$response = $this->_waitForServer('*');

		// Store the last response
		$this->_lastResponse = $response;

		if ($response !== false) {
			$tag = $this->_lastResponse->getName();
		} else {
			$tag = null;
		}

		// Return what type of tag has come back
		return $tag;

	}

	public function isMucSupported()
	{

		// Set up return value. Assume MUC isn't supported
		$mucSupported = false;

		// If items is empty then we haven't yet asked the server what items
		// are associated with it. Query the server for what items are
		// available.
		if (is_null($this->_items)) {
			$this->_discoverItems();
		}

		// Iterate over the items and the main server to ask if MUC is supported
		$items = $this->_items;
		$items[] = array('jid' => $this->_realm);

		foreach ($items as $item) {

			// Send iq stanza asking if this server supports MUC.
			$message = "<iq from='" . $this->_userName . '@' . $this->_realm . '/'
					 . $this->_resource . "' id='disco1' "
					 . "to='" . $item['jid'] . "' type='get'>"
					 . "<query xmlns='http://jabber.org/protocol/disco#info'/>"
					 . "</iq>";
			$this->_stream->send($message);
			$this->_logger->debug('Querying for MUC support');

			// Wait for iq response
			$response = false;
			while(!$response) {
				$response = $this->_waitForServer('iq');
			}
			$this->_logger->debug('Received: ' . $response->asXML());

			// Check if feature tag with appropriate var value is in response.
			// If it is, then MUC is supported
			if (isset($response->query)) {
				foreach ($response->query->children() as $feature) {
					if ($feature->getName() == 'feature'
						&& isset($feature->attributes()->var)
						&& $feature->attributes()->var == 'http://jabber.org/protocol/muc') {
						$mucSupported = true;
					}
				}
			}

		}

		return $mucSupported;

	}

	public function join($roomJid, $nick, $overRideReservedNick = false) {

		// If we shouldn't over ride the reserved nick, check to see if one is 
		// set.
		if (!$overRideReservedNick) {
			// Make a request to see if we have a reserved nick name in the room
			// that we want to join.
			$reservedNick = $this->_requestReservedNickname($roomJid);

			if (!is_null($reservedNick)) {
				$nick = $reservedNick;
			}
		}

		// Attempt to enter the room by sending it a presence element.
		$message = "<presence from='" . $this->_userName . '@' . $this->_realm
				 . '/' . $this->_resource . "' to='" . $roomJid . '/' . $nick
				 . "'><x xmlns='http://jabber.org/protocol/muc'/></presence>";
		$this->_stream->send($message);
		$this->_logger->debug('Attempting to join the room ' . $roomJid);

		// Should now get a list of presences back containing the details of all
		// the other occupants of the room.
		$response = false;
		while(!$response) {
			$response = $this->_waitForServer('presence');
		}
		$this->_logger->debug('Received: ' . $response->asXML());

		// Room has now been joined, if it isn't the array of joinedRooms, add
		// it
		if (!in_array($roomJid, $this->_joinedRooms)) {
			$this->_joinedRooms[] = $roomJid;
		}

		return true;

	}

	/**
	 * Sends a message
	 *
	 * @param string $to
	 * @param string $text
	 */
	public function message($to, $text)
	{
		if (in_array($to, $this->_joinedRooms)) {
			$type = 'groupchat';
		} else {
			$type = 'normal';
		}

		$message = "<message to='" . $to . "' from='" . $this->_userName . '@'
				 . $this->_realm . '/' . $this->_resource . "' type='" . $type
				 . "' xml:lang='en'><body>" . htmlentities($text) . "</body></message>";
		$this->_stream->send($message);

		return true;
	}
	
	public function ping()
	{
		$message = "<iq to='" . $this->_realm . "' from='" . $this->_userName . '@'
				 . $this->_realm . '/' . $this->_resource . "' type='get' "
				 . "id='" . uniqid() . "'>"
				 . "<ping xmlns='urn:xmpp:ping'/>"
				 . "</iq>";
		$this->_stream->send($message);
		
		return true;
	}

	/**
	 * Class destructor. WIll try and close the connection if it is open
	 */
	public function __destruct()
	{
		if (!is_null($this->_stream) && $this->_stream->isConnected()) {
			$this->_stream->send('</stream:stream>');
			$this->_logger->debug('Stream closed');
		}
	}

	protected function _getServer()
	{
		return 'tcp://' . $this->_host . ':' . $this->_port;
	}

	/**
	 * Gets a Stream object that encapsulates the actual connection to the 
	 * server
	 *
	 * @param string   $remoteSocket Address to connect to
	 * @param int      $timeOut      Length of time to wait for connection
	 * @param int      $flags        Flags to be set on the connection
	 * @param resource $context      Context of the connection
	 * 
	 * @return Stream
	 */
	protected function _getStream(
		$remoteSocket, $timeOut = null, $flags = null, $context = null
	) {
		return new Stream($remoteSocket, $timeOut, $flags, $context);
	}

	/**
	 * Gets the logging class
	 *
	 * @param int $logLevel Logging level to be used
	 * 
	 * @return Zend_Log
	 */
	protected function _getLogger($logLevel)
	{
		$writer = new Zend_Log_Writer_Stream('php://output');
		return new Zend_Log($writer);
	}

	protected function _mechanismAvailable($mechanism)
	{
		return in_array($mechanism, $this->_mechanisms);
	}

	protected function _discoverItems()
	{
		// Send IQ stanza asking server what items are associated with it.
		$message = "<iq from='" . $this->_userName . '@' . $this->_realm . '/'
				 . $this->_resource . "' id='" . uniqid() . "' "
				 . "to='" . $this->_realm . "' type='get'>"
				 . "<query xmlns='http://jabber.org/protocol/disco#items'/>"
				 . '</iq>';
		$this->_stream->send($message);
		$this->_logger->debug('Querying for available services');

		// Wait for iq response
		$response = false;
		while(!$response) {
			$response = $this->_waitForServer('iq');
		}
		$this->_logger->debug('Received: ' . $response->asXML());

		// Check if query tag is in response. If it is, then iterate over the
		// children to get the items available.
		if (isset($response->query)) {
			foreach ($response->query->children() as $item) {
				if ($item->getName() == 'item'
					&& isset($item->attributes()->jid)
					&& isset($item->attributes()->name)) {

					// If items is null then we need to turn it into an array.
					if (is_null($this->_items)) {
						$this->_items = array();
					}

					$this->_items[] = array(
						'jid'  => $item->attributes()->jid,
						'name' => $item->attributes()->name,
					);
					
				}
			}
		}
	}

	protected function _requestReservedNickname($roomJid) {

		$message = "<iq from='" . $this->_userName . '@' . $this->_realm . '/'
				 . $this->_resource . "' id='" . uniqid() . "' "
				 . "to='" . $roomJid . "' type='get'>"
				 . "<query xmlns='http://jabber.org/protocol/disco#info' "
				 . "node='x-roomuser-item'/></iq>";
		$this->_stream->send($message);
		$this->_logger->debug('Querying for reserved nickname in ' . $roomJid);

		// Wait for iq response
		$response = false;
		while(!$response) {
			$response = $this->_waitForServer('iq');
		}
		$this->_logger->debug('Received: ' . $response->asXML());

		// If query isn't empty then the user does have a reserved nickname.
		if (isset($response->query) && count($response->query->children()) > 0
			&& isset($response->query->identity)
		) {
			$reservedNick = $response->query->identity->attributes()->name;
		} else {
			$reservedNick = null;
		}

		return $reservedNick;

	}

	protected function _setMechanisms($features)
	{

		// Set up an array to hold any matches
		$matches = array();

		// A response containing a stream:features tag should have been passed
		// in. That should contain a mechanisms tag. Find the mechanisms tag and
		// load it into a SimpleXMLElement object.
		if (preg_match('/<stream:features.*(<mechanisms.*<\/mechanisms>).*<\/stream:features>/', $features->asXml(), $matches) != 0) {

			// Clear out any existing mechanisms
			$this->_mechanisms = array();

			// Create SimpleXMLElement
			$xml = simplexml_load_string($matches[1]);

			foreach($xml->children() as $child) {
				$this->_mechanisms[] = (string)$child;
			}

		}
	}

	protected function _startStream()
	{
		$message = '<stream:stream to="' . $this->_host . '" '
					 . 'xmlns:stream="http://etherx.jabber.org/streams" '
					 . 'xmlns="jabber:client" version="1.0">';
		$this->_stream->send($message);
		$this->_logger->debug('Stream started');
	}

	/**
	 * Waits for the server to send the specified tag back.
	 *
	 * @param string  $tag               Tag to wait for from the server.
	 * 
	 * @return boolean|SimpleXMLElement
	 */
	protected function _waitForServer($tag)
	{

		$fromServer = false;

		// Wait for the stream to update
		if ($this->_stream->select() > 0) {

			$response = '';

			// Continue reading from the connection until it ends in a '>' or
			// we get no more data. Probably a little imprecise, but we can
			// improve this later if needs be.
			while (strrpos($response, '>') != strlen($response) - 1) {
				$response .= $this->_stream->read(4096);
			}
			echo $response . "\n";

			// If the response isn't empty, load it into a SimpleXML element
			if (trim($response) != '') {

				// If the response from the server starts (where "starts
				// with" means "appears after the xml prologue if one is
				// present") with "<stream:stream and it doesn't have a
				// closing "</stream:stream>" then we should append one so
				// that it can be easily loaded into a SimpleXMLElement,
				// otherwise it will cause an error to be thrown because of
				// malformed XML.

				// Check if response starts with XML Prologue:
				if (preg_match("/^<\?xml version='1.0'( encoding='UTF-8')?\?>/", $response, $matches) == 1) {
					$offset = strlen($matches[0]);
					$prologue = $matches[0];
				} else {
					$offset = 0;
				}

				// Check if first part of the actual response starts with
				// <stream:stream
				if (strpos($response, '<stream:stream ') === $offset) {
					// If so, append a closing tag
					$response .= '</stream:stream>';
				}

				// For consistent handling and correct stream namespace
				// support, we should wrap all responses in the
				// stream:stream tags to make sure everything works as
				// expected. Unless the response already contains such tags.
				if (strpos($response, '<stream:stream') === false) {
					$response = '<stream:stream '
							  . 'xmlns:stream="http://etherx.jabber.org/streams" '
							  . "xmlns:ack='http://www.xmpp.org/extensions/xep-0198.html#ns' "
							  . 'xmlns="jabber:client" '
							  . 'from="' . $this->_realm . '" '
							  . 'xml:lang="en" version="1.0">'
							  . $response . '</stream:stream>';
				}

				// If the xml prologue should be at the start, move it
				// because it will now be in the wrong place. We can assume
				// if $offset is not 0 that there was a prologue.
				if ($offset != 0) {
					$response = $prologue
							  . str_replace($prologue, '', $response);
				}

				$xml = simplexml_load_string($response);

				$name = $xml->getName();

				// If we want the stream element itself, just return that,
				// otherwise check the contents of the stream.
				if ($tag == 'stream:stream') {
					$fromServer = $xml;
				} else if ($xml instanceof SimpleXMLElement
						   && $xml->getName() == 'stream') {

					// Get the namespaces used at the root level of the
					// document. Add a blank namespace on for anything that
					// isn't namespaced. Then we can iterate over all of the
					// elements in the doc.
					$namespaces = $xml->getNamespaces();
					$namespaces['blank'] = '';
					foreach ($namespaces as $namespace) {
						foreach ($xml->children($namespace) as $child) {
							if ($tag == '*' || ($child instanceof SimpleXMLElement && $child->getName() == $tag)) {
								$fromServer = $child;
							}
						}
					}
				}
			}

		}

		return $fromServer;
		
	}

}