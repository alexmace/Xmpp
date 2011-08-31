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

error_reporting(E_ALL | E_STRICT);
set_include_path(
    realpath(dirname(__FILE__) . '/..') . PATH_SEPARATOR . get_include_path()
);