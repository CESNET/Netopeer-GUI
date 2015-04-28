<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class CreateFormCest
{
	public function _before(TestGuy $I)
	{
		$I->amOnPage('/login');
		$I->submitForm('#login-form', array('_username' => 'admin', '_password' => 'pass'));
		$I->fillField('Host', 'localhost');
		$I->fillField('Port', '830');
		$I->fillField('User', CommonScenarios::$deviceUser);
		$I->fillField('Password', CommonScenarios::$devicePass);
		$I->click('Connect');
	}

	public function _after(TestGuy $I)
	{
		$I->amOnPage('/logout');
	}

	// tests
	public function submitCreateForm(TestGuy $I)
	{
		$I->wantTo('submit create form test');
		$I->amOnPage('/sections/0/');

//	    $I->seeCurrentUrlEquals('/sections/0/nacm/');

		$data['newNodeForm'] = array(
				'parent'                          => '--*?1!',
				'label0_--*?1!--*?1!'             => 'groups',
				'value0_--*?1!--*?1!'             => '',
				'label1_--*?1!--*?1!--*?1!'       => 'group',
				'value1_--*?1!--*?1!--*?1!'       => '',
				'label2_--*?1!--*?1!--*?1!--*?1!' => 'name',
				'value2_--*?1!--*?1!--*?1!--*?1!' => 'bestsupertestever',
		);
		$I->sendAjaxPostRequest('/sections/0/nacm/', $data);

		// check what is in answer
		$I->see('block--state');
		$I->see('bestsupertestever');
	}

	public function submitRemoveForm(TestGuy $I)
	{
		$I->wantTo('submit delete form test');
		$I->amOnPage('/sections/0/nacm/');

		$data['removeNodeForm'] = array(
				'parent' => '-*--*%3F2!',
		);
		$I->sendAjaxPostRequest('/sections/0/nacm/', $data);

		// check what is in answer
		$I->see('block--state');
		$I->dontSee('write-default');
	}

	public function submitEditConfigForm(TestGuy $I)
	{
		$I->wantTo('submit edit config form test');
		$I->amOnPage('/sections/0/nacm/');

		$data['configDataForm'] = array(
				'enable-nacm_--*--*?1!' => 'false',
		    'write-default_--*--*?2!' => 'deny',
		);
		$I->sendAjaxPostRequest('/sections/0/nacm/', $data);

		// check what is in answer
		$I->see('block--state');
		$I->dontSee('permit');
	}

	public function submitEmptyModuleForm(TestGuy $I)
	{
		$I->wantTo('submit empty module form test');
		$I->amOnPage('/sections/create-empty-module/0/');

		// remove root element
		$data['form'] = array(
				'name' => 'notification',
		    'namespace' => 'urn:ietf:params:xml:ns:netconf:notification:1.0',
		);
		$I->submitForm('form[name=formCreateEmptyModule]', $data);

		// check what is in answer
		$I->see('block--state');
	}
}