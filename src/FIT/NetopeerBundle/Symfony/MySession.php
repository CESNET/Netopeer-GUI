<?php
/**
 * BaseController as parent of  all controllers in this bundle handles all common functions
 * such as assigning template variables, menu structure...
 *
 * @file BaseController.php
 * @author David Alexa <alexa.david@me.com>
 *
 * Copyright (C) 2012-2013 CESNET
 *
 * LICENSE TERMS
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 * 3. Neither the name of the Company nor the names of its contributors
 *    may be used to endorse or promote products derived from this
 *    software without specific prior written permission.
 *
 * ALTERNATIVELY, provided that this notice is retained in full, this
 * product may be distributed under the terms of the GNU General Public
 * License (GPL) version 2 or later, in which case the provisions
 * of the GPL apply INSTEAD OF those given above.
 *
 * This software is provided ``as is'', and any express or implied
 * warranties, including, but not limited to, the implied warranties of
 * merchantability and fitness for a particular purpose are disclaimed.
 * In no event shall the company or contributors be liable for any
 * direct, indirect, incidental, special, exemplary, or consequential
 * damages (including, but not limited to, procurement of substitute
 * goods or services; loss of use, data, or profits; or business
 * interruption) however caused and on any theory of liability, whether
 * in contract, strict liability, or tort (including negligence or
 * otherwise) arising in any way out of the use of this software, even
 * if advised of the possibility of such damage.
 *
 */
namespace FIT\NetopeerBundle\Symfony;

use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;

/**
 * mySession object.
 */
class mySession extends Session
{
	protected $shortFlashes;

	/**
	 * @inheritdoc
	 */
	public function __construct(SessionStorageInterface $storage, $defaultLocale = 'en')
	{
		parent::__construct($storage, $defaultLocale);
		$this->shortFlashes = array();
	}

	/**
	 * @inheritdoc
	 */
	public function setFlash($name, $value)
	{
		parent::setFlash($name, $value);
		$this->setShortFlash($name, $value);
	}

	public function setShortFlash($name, $value) {
		$this->shortFlashes[$name] = $value;
	}

	public function getShortFlashes() {
		return $this->shortFlashes;
	}
}