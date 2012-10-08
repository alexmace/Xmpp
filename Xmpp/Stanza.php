<?php

/**
 * PHP XMPP Library
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
 * Abstract class for representing Xmpp Stanzas.
 *
 * @category  XMPP
 * @package   XMPP
 * @author    Alex Mace <a@m.me.uk>
 * @copyright 2010-2011 Alex Mace (http://m.me.uk)
 * @license   http://m.me.uk/license New BSD License
 * @link      http://m.me.uk/xmpp
 */
abstract class Xmpp_Stanza
{

    private $_from = null;
    private $_to = null;
	private $_id = null;
    protected $type = null;

    /**
     * Class constructor, sets up common class variables.
     *
     * @param SimpleXMLElement $stanza The XML itself for the stanza.
     */
    public function __construct(SimpleXMLElement $stanza)
    {

        if (isset($stanza['from'])) {
            $this->_from = (string) $stanza['from'];
        }

        if (isset($stanza['to'])) {
            $this->_to = (string) $stanza['to'];
        }

		if (isset($stanza['id'])) {
			$this->_id = (string) $stanza['id'];
		}
    }

    /**
     * Returns the JID of the sender of the stanza.
     *
     * @return string
     */
    public function getFrom()
    {
        return $this->_from;
    }

    /**
     * Returns who the JID of who the stanza was sent to.
     *
     * @return string
     */
    public function getTo()
    {
        return $this->_to;
    }

    /**
     * Returns the value of the "type" attribute on the stanza.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

	/**
	 * Returns the "id" of the stanza.
	 *
	 * @return string
	 */
	public function getId()
	{
		return $this->_id;
	}

}