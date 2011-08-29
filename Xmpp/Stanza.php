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
abstract class Xmpp_Stanza
{
	
	private $_from = null;

	private $_to = null;

	protected $_type = null;
 
	public function  __construct(SimpleXMLElement $stanza)
	{

		if (isset($stanza['from'])) {
			$this->_from = (string)$stanza['from'];
		}

		if (isset($stanza['to'])) {
			$this->_to = (string)$stanza['to'];
		}
		
	}


	public function getFrom() {
		return $this->_from;
	}

	public function getTo() {
		return $this->_to;
	}

	public function getType() {
		return $this->_type;
	}

}