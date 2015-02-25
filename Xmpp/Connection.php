<?php

/**
 * Xmpp
 *
 * Xmpp is a implementation of the Xmpp protocol.
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
 * Xmpp is an implementation of the Xmpp protocol. Note that creating the class
 * does not connect to the server specified in the constructor. You need to call
 * connect() to actually perform the connection.
 *
 * @category Xmpp
 * @package  Xmpp
 * @author   Alex Mace <a@m.me.uk>
 * @license  http://m.me.uk/xmpp/license/ New BSD License
 * @link     http://pear.m.me.uk/package/Xmpp
 * @todo Store what features are available when session is established
 * @todo Handle error conditions of authenticate, bind, connect and
 * 		 establishSession
 * @todo Throw exceptions when attempting to perform actions that the server has
 * 		 not reported that they support.
 * @todo ->wait() method should return a class that encapsulates what has come
 * 		 from the server. e.g. Xmpp_Message Xmpp_Iq Xmpp_Presence
 */
class Xmpp_Connection
{
    const PRESENCE_AWAY = 'away';
    const PRESENCE_CHAT = 'chat';
    const PRESENCE_DND = 'dnd';
    const PRESENCE_XA = 'xa';

    /**
     * Holds the buffer of incoming tags
     *
     * @var array
     */
    private $_buffer = array();

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
    protected $items = null;

    /**
     * Holds an array of rooms that have been joined on this connection.
     *
     * @var array
     */
    protected $joinedRooms = array();
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
        $this->_logger = $this->getLogger($logLevel);
        $this->_password = $password;
        $this->_port = $port;
        $this->_resource = $resource;
        $this->_ssl = $ssl;
        list($this->_userName, $this->_realm)
            = array_pad(explode('@', $userName), 2, null);
    }

    /**
     * Authenticate against server with the stored username and password.
     *
     * Note only DIGEST-MD5 authentication is supported.
     *
     * @return boolean
     */
    public function authenticate()
    {
        // Check that the server said that DIGEST-MD5 was available
        if ($this->mechanismAvailable('DIGEST-MD5')) {

            // Send message to the server that we want to authenticate
            $message = "<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' "
                    . "mechanism='DIGEST-MD5'/>";
            $this->_logger->debug('Requesting Authentication: ' . $message);
            $this->_stream->send($message);

            // Wait for challenge to come back from the server
            $response = $this->waitForServer('challenge');
            $this->_logger->debug('Response: ' . $response->asXML());

            // Decode the response
            $decodedResponse = base64_decode((string) $response);
            $this->_logger->debug('Response (Decoded): ' . $decodedResponse);

            // Split up the parts of the challenge
            $challengeParts = explode(',', $decodedResponse);

            // Create an array to hold the challenge
            $challenge = array();

            // Process the parts and put them into the array for easy access
            foreach ($challengeParts as $part) {
                list($key, $value) = explode('=', trim($part), 2);
                $challenge[$key] = trim($value, '"');
            }

			// Ejabberd Doesn't appear to send the realm in the challenge, so
			// we need to default to what we think the realm is.
			if (!isset($challenge['realm'])) {
				$challenge['realm'] = $this->_realm;
			}

            $cnonce = uniqid();
            $a1 = pack(
                'H32',
                md5(
                    $this->_userName . ':' . $challenge['realm'] . ':' .
                    $this->_password
                )
            )
                . ':' . $challenge['nonce'] . ':' . $cnonce;

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
                    . 'charset="' . $challenge['charset'] . '"';
            $this->_logger->debug('Unencoded Response: ' . $message);
            $message = "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>"
                    . base64_encode($message) . '</response>';

            // Send the response
            $this->_logger->debug('Challenge Response: ' . $message);
            $this->_stream->send($message);

            // Should get another challenge back from the server. Some servers
            // don't bother though and just send a success back with the
            // rspauth encoded in it.
            $response = $this->waitForServer('*');
            $this->_logger->debug('Response: ' . $response->asXML());

            // If we have got a challenge, we need to send a response, blank
            // this time.
            if ($response->getName() == 'challenge') {
                $message = "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'/>";

                // Send the response
                $this->_logger->debug('Challenge Response: ' . $message);
                $this->_stream->send($message);

                // This time we should get a success message.
                $response = $this->waitForServer('success');
                $this->_logger->debug('Response: ' . $response->asXML());
            }


            // Now that we have been authenticated, a new stream needs to be
            // started.
            $this->startStream();

            // Server should now respond with start of stream and list of features
            $response = $this->waitForServer('stream:stream');
            $this->_logger->debug('Received: ' . $response);

            // If the server has not yet said what features it supports, wait
            // for that
            if (strpos($response->asXML(), 'stream:features') === false) {
                $response = $this->waitForServer('stream:features');
                $this->_logger->debug('Received: ' . $response);
            }
        } else if ($this->mechanismAvailable ('PLAIN')) {
			
			$auth = base64_encode($this->_userName . '@' . $this->_realm . "\u0000" . $this->_userName . "\u0000" . $this->_password);
			
			// Send message to the server that we want to authenticate
            $message = "<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' "
                    . "mechanism='PLAIN'>$auth</message";
            $this->_logger->debug('Requesting Authentication: ' . $message);
            $this->_stream->send($message);

		}
		

        return true;
    }

    /**
     * Bind this connection to a particular resource (the last part of the JID)
     *
     * @return true
     */
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
        $response = $this->waitForServer('*');
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
        $server = $this->getServer();

        try {

            // Get a connection to server
            $this->_stream = $this->getStream($server);
            $this->_logger->debug('Connection made');

            // Set the stream to blocking mode
            $this->_stream->setBlocking(true);
            $this->_logger->debug('Blocking enabled');

            // Attempt to send the stream start
            $this->startStream();

            $this->_logger->debug('Wait for response from server');

            // Now we will expect to get a stream tag back from the server. Not
            // sure if we're supposed to do anything with it, so we'll just drop
            // it for now. May contain the features the server supports.
            $response = $this->waitForServer('stream:stream');
            $this->_logger->debug('Received: ' . $response);

            // If the response from the server does contain a features tag, don't
            // bother querying server to get it.
            // TODO - Xpath would probably be more sensible for this, but for
            // now this'll work.
            if (strpos($response->asXml(), '<stream:features') === false) {

                // Server should now send back a features tag telling us what
                // features it supports. If it tells us to start tls then we will
                // need to change to a secure connection. It will also tell us what
                // authentication methods it supports.
                //
                // Note we check for a "features" tag rather than stream:features
                // because it is namespaced.
                $response = $this->waitForServer('features');
                $this->_logger->debug('Received: ' . $response);
            }

            // Set mechanisms based on that tag
            $this->setMechanisms($response);

            // If there was a starttls tag in there, and this connection has SSL
            // enabled, then we should tell the server that we will start up tls as
            // well.
            if (preg_match("/<starttls xmlns=('|\")urn:ietf:params:xml:ns:xmpp-tls('|\")>(<required\/>)?<\/starttls>/", $response->asXML()) != 0
                && $this->_ssl === true
            ) {
                $this->_logger->debug('Informing server we will start TLS');
                $message = "<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'/>";
                $this->_stream->send($message);

                // Wait to get the proceed message back from the server
                $response = $this->waitForServer('proceed');
                $this->_logger->debug('Received: ' . $response->asXML());

                // Once we have the proceed signal from the server, we should turn
                // on TLS on the stream and send the opening stream tag again.
                $this->_stream->setTLS(true);
                $this->_logger->debug('Enabled TLS');

                // Now we need to start a new stream again.
                $this->startStream();

                // Server should now respond with start of stream and list of
                // features
                $response = $this->waitForServer('stream:stream');
                $this->_logger->debug('Received: ' . $response);

                // Set mechanisms based on that tag
                $this->setMechanisms($response);
            }
        } catch (Stream_Exception $e) {
            // A Stream Exception occured. Catch it and rethrow it as an Xmpp
            // Exception.
            throw new Xmpp_Exception('Failed to connect: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Disconnect from the server.
     *
     * @return boolean
     */
    public function disconnect()
    {
        $message = '</stream:stream>';

        // If the stream isn't set, get one. Seems unlikely that we'd want to be
        // disconnecting when no connection is open via a stream, but it saves us
        // having to go through the rigormoral of actually setting up a proper, full
        // mock connection.
        if (!isset($this->_stream)) {
            $this->_stream = $this->getStream($this->getServer());
        }

        $this->_stream->send($message);
        $this->_stream->disconnect();
        $this->_logger->debug('Disconnected');

        return true;
    }

    /**
     * Establish the start of a session.
     *
     * @return boolean
     */
    public function establishSession()
    {

        // Send message requesting start of session.
        $message = "<iq to='" . $this->_realm . "' type='set' id='sess_1'>"
                . "<session xmlns='urn:ietf:params:xml:ns:xmpp-session'/>"
                . "</iq>";
        $this->_stream->send($message);

        // Should now get an iq in response from the server to say the session
        // was established.
        $response = $this->waitForServer('iq');
        $this->_logger->debug('Received: ' . $response->asXML());

        return true;
    }

    /**
     * Get the last response as an instance of Xmpp_Iq.
     *
     * @return Xmpp_Iq
     */
    public function getIq()
    {
        if ((string) $this->_lastResponse->getName() != 'iq') {
            throw new Xmpp_Exception('Last stanza received was not an iq stanza');
        }

        return new Xmpp_Iq($this->_lastResponse);
    }

    /**
     * Get the last response an an instance of Xmpp_Message.
     *
     * @return Xmpp_Message
     */
    public function getMessage()
    {
        if ((string) $this->_lastResponse->getName() != 'message') {
            throw new Xmpp_Exception('Last stanza received was not a message');
        }

        return new Xmpp_Message($this->_lastResponse);
    }

    /**
     * Set the presence of the user.
     *
     * @param string $status   Custom status string
     * @param string $show     Current state of user, e.g. away, do not disturb
     * @param int    $priority Presence priority
     *
     * @todo Allow multiple statuses to be entered
     *
     * @return boolean
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
                || $show == self::PRESENCE_XA)
            ) {
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
     * Wait for the server to respond.
     *
     * @todo Get this to return after a timeout period if nothing has come back
     *
     * @return string
     */
    public function wait()
    {

        // Wait for any tag to be sent by the server
        $response = $this->waitForServer('*');

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

    /**
     * Check if server supports the Multi-User Chat extension.
     *
     * @return boolean
     */
    public function isMucSupported()
    {

        // Set up return value. Assume MUC isn't supported
        $mucSupported = false;

        // If items is empty then we haven't yet asked the server what items are
        // associated with it. Query the server for what items are available.
        if (is_null($this->items)) {
            $this->discoverItems();
        }

        // Iterate over the items and the main server to ask if MUC is supported
        $items = $this->items;
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
            while (!$response) {
                $response = $this->waitForServer('iq');
            }
            $this->_logger->debug('Received: ' . $response->asXML());

            // Check if feature tag with appropriate var value is in response.
            // If it is, then MUC is supported
            if (isset($response->query)) {
                foreach ($response->query->children() as $feature) {

                    if ($feature->getName() == 'feature'
                        && isset($feature->attributes()->var)
                        && $feature->attributes()->var == 'http://jabber.org/protocol/muc'
                    ) {

                        $mucSupported = true;
                    }
                }
            }
        }

        return $mucSupported;
    }

    /**
     * Join a MUC Room.
     *
     * @param type $roomJid              Room to join.
     * @param type $nick                 Nickname to join as.
     * @param type $overRideReservedNick Override the server assigned nickname.
     *
     * @return boolean
     */
    public function join($roomJid, $nick, $overRideReservedNick = false)
    {

        // If we shouldn't over ride the reserved nick, check to see if one is
        // set.
        if (!$overRideReservedNick) {
            // Make a request to see if we have a reserved nick name in the room
            // that we want to join.
            $reservedNick = $this->requestReservedNickname($roomJid);

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

        // Should now get a list of presences back containing the details of all the
        // other occupants of the room.
        $response = false;
        while (!$response) {
            $response = $this->waitForServer('presence');
        }
        $this->_logger->debug('Received: ' . $response->asXML());

        // Room has now been joined, if it isn't the array of joinedRooms, add it
        if (!in_array($roomJid, $this->joinedRooms)) {
            $this->joinedRooms[] = $roomJid;
        }

        return true;
    }

    /**
     * Sends a message.
     *
     * @param string $to   Intended recipient of the message.
     * @param string $text Contents of the message.
     *
     * @return boolean
     */
    public function message($to, $text)
    {

        // Get the first part of the JID
        $firstPart = array_shift(explode('/', $to));

        if (in_array($firstPart, $this->joinedRooms)) {
            $type = 'groupchat';
            $to = $firstPart;
        } else {
            $type = 'normal';
        }

        $message = "<message to='" . $to . "' from='" . $this->_userName . '@'
                . $this->_realm . '/' . $this->_resource . "' type='" . $type
                . "' xml:lang='en'><body>" . $this->encode($text)
                . "</body></message>";
        $this->_stream->send($message);

        return true;
    }

    /**
     * Send a ping to the server.
     *
     * @return boolean
     */
    public function ping($to)
    {
        $message = "<iq to='" . $to . "' from='" . $this->_userName . '@'
                . $this->_realm . '/' . $this->_resource . "' type='get' "
                . "id='" . uniqid() . "'>"
                . "<ping xmlns='urn:xmpp:ping'/>"
                . "</iq>";
        $this->_stream->send($message);

        return true;
    }

	/**
	 * Send a response to a ping.
	 *
	 * @param string $to Who the response is being sent back to.
	 * @param string $id The ID from the original ping.
	 *
	 * @return boolean
	 */
	public function pong($to, $id)
	{
		$message = "<iq from='" . $this->_userName . '@' . $this->_realm . '/'
				 . $this->_resource . "' to='" . $to . "' id='" . $id . "' "
				 . "type='result'/>";
		$this->_stream->send($message);

		return true;
	}

    /**
     * Class destructor. Will try and close the connection if it is open
     */
    public function __destruct()
    {
        if (!is_null($this->_stream) && $this->_stream->isConnected()) {
            $this->_stream->send('</stream:stream>');
            $this->_logger->debug('Stream closed');
        }
    }

    /**
     * Get the server this class should connect to.
     *
     * @return string
     */
    protected function getServer()
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
    protected function getStream(
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
    protected function getLogger($logLevel)
    {
        $writer = new Zend_Log_Writer_Stream('php://output');
        return new Zend_Log($writer);
    }

    /**
     * Checks if a given authentication mechanism is available.
     *
     * @param string $mechanism Mechanism to check availability for.
     *
     * @return boolean
     */
    protected function mechanismAvailable($mechanism)
    {
        return in_array($mechanism, $this->_mechanisms);
    }

    /**
     * Discovers what items (basically features) are available on the server.
     *
     * @return void
     */
    protected function discoverItems()
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
        while (!$response || $response->getName() != 'iq'
            || strpos($response->asXml(), '<item') === false
        ) {

            $response = $this->waitForServer('iq');
        }
        $this->_logger->debug('Received: ' . $response->asXML());

        // Check if query tag is in response. If it is, then iterate over the
        // children to get the items available.
        if (isset($response->query)) {
            foreach ($response->query->children() as $item) {
                if ($item->getName() == 'item'
                    && isset($item->attributes()->jid)
                    && isset($item->attributes()->name)
                ) {

                    // If items is null then we need to turn it into an array.
                    if (is_null($this->items)) {
                        $this->items = array();
                    }

                    $this->items[] = array(
                        'jid' => $item->attributes()->jid,
                        'name' => $item->attributes()->name,
                    );
                }
            }
        }
    }

    /**
     * Encodes text for XML.
     *
     * Most inspired by this example:
     * http://www.sourcerally.net/Scripts/39-Convert-HTML-Entities-to-XML-Entities
     *
     * @param string $text
     * @return string
     */
    private function encode($text)
    {
        $text = htmlentities($text, ENT_COMPAT, 'UTF-8');
        $xml = array(
            '&#34;','&#38;','&#38;','&#60;','&#62;','&#160;','&#161;','&#162;',
            '&#163;','&#164;','&#165;','&#166;','&#167;','&#168;','&#169;','&#170;',
            '&#171;','&#172;','&#173;','&#174;','&#175;','&#176;','&#177;','&#178;',
            '&#179;','&#180;','&#181;','&#182;','&#183;','&#184;','&#185;','&#186;',
            '&#187;','&#188;','&#189;','&#190;','&#191;','&#192;','&#193;','&#194;',
            '&#195;','&#196;','&#197;','&#198;','&#199;','&#200;','&#201;','&#202;',
            '&#203;','&#204;','&#205;','&#206;','&#207;','&#208;','&#209;','&#210;',
            '&#211;','&#212;','&#213;','&#214;','&#215;','&#216;','&#217;','&#218;',
            '&#219;','&#220;','&#221;','&#222;','&#223;','&#224;','&#225;','&#226;',
            '&#227;','&#228;','&#229;','&#230;','&#231;','&#232;','&#233;','&#234;',
            '&#235;','&#236;','&#237;','&#238;','&#239;','&#240;','&#241;','&#242;',
            '&#243;','&#244;','&#245;','&#246;','&#247;','&#248;','&#249;','&#250;',
            '&#251;','&#252;','&#253;','&#254;','&#255;','&#338;','&#339;','&#352;',
            '&#353;','&#376;','&#402;','&#710;','&#732;','&#913;','&#914;','&#915;',
            '&#916;','&#917;','&#918;','&#919;','&#920;','&#921;','&#922;','&#923;',
            '&#924;','&#925;','&#926;','&#927;','&#928;','&#929;','&#931;','&#932;',
            '&#933;','&#934;','&#935;','&#936;','&#937;','&#945;','&#946;','&#947;',
            '&#948;','&#949;','&#950;','&#951;','&#952;','&#953;','&#954;','&#955;',
            '&#956;','&#957;','&#958;','&#959;','&#960;','&#961;','&#962;','&#963;',
            '&#964;','&#965;','&#966;','&#967;','&#968;','&#969;','&#977;','&#978;',
            '&#982;','&#8194;','&#8195;','&#8201;','&#8204;','&#8205;','&#8206;',
            '&#8207;','&#8211;','&#8212;','&#8216;','&#8217;','&#8218;','&#8220;',
            '&#8221;','&#8222;','&#8224;','&#8225;','&#8226;','&#8230;','&#8240;',
            '&#8242;','&#8243;','&#8249;','&#8250;','&#8254;','&#8364;','&#8482;',
            '&#8592;','&#8593;','&#8594;','&#8595;','&#8596;','&#8629;','&#8704;',
            '&#8706;','&#8707;','&#8709;','&#8711;','&#8712;','&#8713;','&#8715;',
            '&#8719;','&#8721;','&#8722;','&#8727;','&#8730;','&#8733;','&#8734;',
            '&#8736;','&#8743;','&#8744;','&#8745;','&#8746;','&#8747;','&#8756;',
            '&#8764;','&#8773;','&#8776;','&#8800;','&#8801;','&#8804;','&#8805;',
            '&#8834;','&#8835;','&#8836;','&#8838;','&#8839;','&#8853;','&#8855;',
            '&#8869;','&#8901;','&#8968;','&#8969;','&#8970;','&#8971;','&#9674;',
            '&#9824;','&#9827;','&#9829;','&#9830;',
        );

        $html = array(
            '&quot;','&amp;','&amp;','&lt;','&gt;','&nbsp;','&iexcl;','&cent;',
            '&pound;','&curren;','&yen;','&brvbar;','&sect;','&uml;','&copy;',
            '&ordf;','&laquo;','&not;','&shy;','&reg;','&macr;','&deg;','&plusmn;',
            '&sup2;','&sup3;','&acute;','&micro;','&para;','&middot;','&cedil;',
            '&sup1;','&ordm;','&raquo;','&frac14;','&frac12;','&frac34;','&iquest;',
            '&Agrave;','&Aacute;','&Acirc;','&Atilde;','&Auml;','&Aring;','&AElig;',
            '&Ccedil;','&Egrave;','&Eacute;','&Ecirc;','&Euml;','&Igrave;',
            '&Iacute;','&Icirc;','&Iuml;','&ETH;','&Ntilde;','&Ograve;','&Oacute;',
            '&Ocirc;','&Otilde;','&Ouml;','&times;','&Oslash;','&Ugrave;','&Uacute;',
            '&Ucirc;','&Uuml;','&Yacute;','&THORN;','&szlig;','&agrave;','&aacute;',
            '&acirc;','&atilde;','&auml;','&aring;','&aelig;','&ccedil;','&egrave;',
            '&eacute;','&ecirc;','&euml;','&igrave;','&iacute;','&icirc;','&iuml;',
            '&eth;','&ntilde;','&ograve;','&oacute;','&ocirc;','&otilde;','&ouml;',
            '&divide;','&oslash;','&ugrave;','&uacute;','&ucirc;','&uuml;',
            '&yacute;','&thorn;','&yuml;', '&OElig;','&oelig;','&Scaron;','&scaron;',
            '&Yuml;','&fnof;','&circ;','&tilde;','&Alpha;','&Beta;','&Gamma;',
            '&Delta;','&Epsilon;','&Zeta;','&Eta;','&Theta;','&Iota;','&Kappa;',
            '&Lambda;','&Mu;','&Nu;','&Xi;','&Omicron;','&Pi;','&Rho;','&Sigma;',
            '&Tau;','&Upsilon;','&Phi;','&Chi;','&Psi;','&Omega;','&alpha;','&beta;',
            '&gamma;','&delta;','&epsilon;','&zeta;','&eta;','&theta;','&iota;',
            '&kappa;','&lambda;','&mu;','&nu;','&xi;','&omicron;','&pi;','&rho;',
            '&sigmaf;','&sigma;','&tau;','&upsilon;','&phi;','&chi;','&psi;',
            '&omega;','&thetasym;','&upsih;','&piv;','&ensp;','&emsp;','&thinsp;',
            '&zwnj;','&zwj;','&lrm;','&rlm;','&ndash;','&mdash;','&lsquo;','&rsquo;',
            '&sbquo;','&ldquo;','&rdquo;','&bdquo;','&dagger;','&Dagger;','&bull;',
            '&hellip;','&permil;','&prime;','&Prime;','&lsaquo;','&rsaquo;',
            '&oline;','&euro;','&trade;','&larr;','&uarr;','&rarr;','&darr;',
            '&harr;','&crarr;','&forall;','&part;','&exist;','&empty;','&nabla;',
            '&isin;','&notin;','&ni;','&prod;','&sum;','&minus;','&lowast;',
            '&radic;','&prop;','&infin;','&ang;','&and;','&or;','&cap;','&cup;',
            '&int;','&there4;','&sim;','&cong;','&asymp;','&ne;','&equiv;','&le;',
            '&ge;','&sub;','&sup;','&nsub;','&sube;','&supe;','&oplus;','&otimes;',
            '&perp;','&sdot;','&lceil;','&rceil;','&lfloor;','&rfloor;','&loz;',
            '&spades;','&clubs;','&hearts;','&diams;',
        );
        $text = str_replace($html, $xml, $text);
        $text = str_ireplace($html, $xml, $text);
        return $text;
    }

    /**
     * Checks if the server has a reserved nickname for this user in the given room.
     *
     * @param string $roomJid Given room the check the reserved nicknames for.
     *
     * @return string
     */
    protected function requestReservedNickname($roomJid)
    {

        $message = "<iq from='" . $this->_userName . '@' . $this->_realm . '/'
                . $this->_resource . "' id='" . uniqid() . "' "
                . "to='" . $roomJid . "' type='get'>"
                . "<query xmlns='http://jabber.org/protocol/disco#info' "
                . "node='x-roomuser-item'/></iq>";
        $this->_stream->send($message);
        $this->_logger->debug('Querying for reserved nickname in ' . $roomJid);

        // Wait for iq response
        $response = false;
        while (!$response) {
            $response = $this->waitForServer('iq');
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

    /**
     * Take the given features tag and figure out what authentication mechanisms are
     * supported from it's contents.
     *
     * @param SimpleXMLElement $features <stream:features> saying what server
     *                                   supports
     *
     * @return void
     */
    protected function setMechanisms(SimpleXMLElement $features)
    {

        // Set up an array to hold any matches
        $matches = array();

        // A response containing a stream:features tag should have been passed in.
        // That should contain a mechanisms tag. Find the mechanisms tag and load it
        // into a SimpleXMLElement object.
        if (preg_match('/<stream:features.*(<mechanisms.*<\/mechanisms>).*<\/stream:features>/', $features->asXml(), $matches) != 0) {

            // Clear out any existing mechanisms
            $this->_mechanisms = array();

            // Create SimpleXMLElement
            $xml = simplexml_load_string($matches[1]);

            foreach ($xml->children() as $child) {
                $this->_mechanisms[] = (string) $child;
            }
        }
    }

    /**
     * Starts an XMPP connection.
     *
     * @return void
     */
    protected function startStream()
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
     * @param string $tag Tag to wait for from the server.
     *
     * @return boolean|SimpleXMLElement
     */
    protected function waitForServer($tag)
    {

		$this->_logger->debug("Tag we're waiting for: " . $tag);

        $fromServer = false;

        // If there is nothing left in the buffer, wait for the stream to update
        if (count($this->_buffer) == 0 && $this->_stream->select() > 0) {

            $response = '';

            $done = false;

            // Read data from the connection.
            while (!$done) {
                $response .= $this->_stream->read(4096);
                if ($this->_stream->select() == 0) {
                    $done = true;
                }
            }

			$this->_logger->debug('Response (Xmpp_Connection): ' . $response);

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

                // If we want the stream element itself, just return that,
                // otherwise check the contents of the stream.
                if ($tag == 'stream:stream') {
                    $fromServer = $xml;
                } else if ($xml instanceof SimpleXMLElement
                    && $xml->getName() == 'stream'
                ) {

                    // Get the namespaces used at the root level of the
                    // document. Add a blank namespace on for anything that
                    // isn't namespaced. Then we can iterate over all of the
                    // elements in the doc.
                    $namespaces = $xml->getNamespaces();
                    $namespaces['blank'] = '';
                    foreach ($namespaces as $namespace) {
                        foreach ($xml->children($namespace) as $child) {
                            if ($child instanceof SimpleXMLElement) {
                                $this->_buffer[] = $child;
                            }
                        }
                    }
                }
            }
        }

		$this->_logger->debug('Contents of $fromServer: ' . var_export($fromServer, true));
		$this->_logger->debug('Contents of $this->_buffer before foreach: ' . var_export($this->_buffer, true));

        // Now go over what is in the buffer and return anything necessary
        foreach ($this->_buffer as $key => $stanza) {

            // Only bother looking for more tags if one has not yet been found.
            if ($fromServer === false) {

                // Remove this element from the buffer because we do not want it to
                // be processed again.
                unset($this->_buffer[$key]);

                // If this the tag we want, save it for returning.
                if ($tag == '*' || $stanza->getName() == $tag) {
                    $fromServer = $stanza;
                }

            }
        }

		$this->_logger->debug('Contents of $this->_buffer after foreach: ' . var_export($this->_buffer, true));

        return $fromServer;
    }

}