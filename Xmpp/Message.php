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
 * Represents an XMPP <message> 
 *
 * @category  XMPP
 * @package   XMPP
 * @author    Alex Mace <a@m.me.uk>
 * @copyright 2010-2011 Alex Mace (http://m.me.uk)
 * @license   http://m.me.uk/license New BSD License
 * @link      http://m.me.uk/xmpp
 */
class Xmpp_Message extends Xmpp_Stanza
{
    const TYPE_CHAT = 'chat';
    const TYPE_ERROR = 'error';
    const TYPE_GROUPCHAT = 'groupchat';
    const TYPE_HEADLINE = 'headline';
    const TYPE_NORMAL = 'normal';

    private $_bodies = array();
    private $_error = null;
    private $_lang = null;
    private $_subjects = array();
    private $_thread = null;
	private $_delayed = false;

    /**
     * Class constructor.
     * 
     * @param SimpleXMLElement $message The XML of the message.
     */
    public function __construct(SimpleXMLElement $message)
    {

        parent::__construct($message);

        // Get the type of the message
        if (isset($message['type'])
            && ((string) $message['type'] == self::TYPE_CHAT
            || (string) $message['type'] == self::TYPE_ERROR
            || (string) $message['type'] == self::TYPE_GROUPCHAT
            || (string) $message['type'] == self::TYPE_HEADLINE)
        ) {
            $this->type = (string) $message['type'];
        } else {
            $this->type = self::TYPE_NORMAL;
        }

        if ($this->type == self::TYPE_ERROR) {
            if (isset($message->error[0])) {
                $this->_error = (string) $message->error[0];
            } else {
                $this->_error = '';
            }
        }

        if (isset($message['xml:lang'])) {
            $this->_lang = (string) $message['xml:lang'];
        }

        foreach ($message->subject as $subject) {
            $thisSubject = array(
                'content' => (string) $subject,
            );

            if (isset($subject['xml:lang'])) {
                $thisSubject['lang'] = (string) $subject['xml:lang'];
            }

            $this->_subjects[] = $thisSubject;
        }

        foreach ($message->body as $body) {
            $thisBody = array(
                'content' => (string) $body,
            );

            if (isset($body['xml:lang'])) {
                $thisBody['lang'] = (string) $body['xml:lang'];
            }

            $this->_bodies[] = $thisBody;
        }
		
		if (isset($message->delay[0])) {
			$this->_delayed = true;
		}

        if (isset($message->thread[0])) {
            $this->_thread = (string) $message->thread[0];
        } else {
            $this->_thread = '';
        }
    }

    /**
     * Gets the bodies contained in the message.
     * 
     * @return type 
     */
    public function getBodies()
    {
        return $this->_bodies;
    }

    /**
     * Gets the error associated with this message.
     * 
     * @return type 
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Gets the language of the message.
     * 
     * @return type 
     */
    public function getLang()
    {
        return $this->_lang;
    }

    /**
     * Gets the subjects of the message.
     * 
     * @return type 
     */
    public function getSubjects()
    {
        return $this->_subjects;
    }

    /**
     * Gets the thread the message is associated with.
     * 
     * @return type 
     */
    public function getThread()
    {
        return $this->_thread;
    }
	
	public function isDelayed()
	{
		return $this->_delayed;
	}

}