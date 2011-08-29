<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Message
 *
 * @author alex
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
 
	public function  __construct(SimpleXMLElement $message)
	{
		
		parent::__construct($message);

		// Get the type of the message
		if (isset($message['type'])
			&& ((string)$message['type'] == self::TYPE_CHAT
				|| (string)$message['type'] == self::TYPE_ERROR
				|| (string)$message['type'] == self::TYPE_GROUPCHAT
				|| (string)$message['type'] == self::TYPE_HEADLINE)) {
			$this->_type = (string)$message['type'];
		} else {
			$this->_type = self::TYPE_NORMAL;
		}

		if ($this->_type == self::TYPE_ERROR) {
			if (isset($message->error[0])) {
				$this->_error = (string)$message->error[0];
			} else {
				$this->_error = '';
			}
		}

		if (isset($message['xml:lang'])) {
			$this->_lang = (string)$message['xml:lang'];
		}

		foreach ($message->subject as $subject) {
			$thisSubject = array(
				'content' => (string)$subject,
			);

			if (isset($subject['xml:lang'])) {
				$thisSubject['lang'] = (string)$subject['xml:lang'];
			}

			$this->_subjects[] = $thisSubject;
		}

		foreach ($message->body as $body) {
			$thisBody = array(
				'content' => (string)$body,
			);

			if (isset($body['xml:lang'])) {
				$thisBody['lang'] = (string)$body['xml:lang'];
			}

			$this->_bodies[] = $thisBody;
		}


		if (isset($message->thread[0])) {
			$this->_thread = (string)$message->thread[0];
		} else {
			$this->_thread = '';
		}
		
	}

	public function getBodies() {
		return $this->_bodies;
	}
	
	public function getError() {
		return $this->_error;
	}

	public function getLang() {
		return $this->_lang;
	}

	public function getSubjects() {
		return $this->_subjects;
	}

	public function getThread() {
		return $this->_thread;
	}

}