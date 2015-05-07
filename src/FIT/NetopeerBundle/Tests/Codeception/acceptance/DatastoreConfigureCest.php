<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class DatastoreConfigureCest
{

	public function _before(WebGuy $I)
	{

	}

	public function _after(WebGuy $I)
	{
		$I->amOnPage('/logout');
	}

	public function _login(WebGuy $I) {
		CommonScenarios::login($I);
		CommonScenarios::connectToLocalhostDevice($I);
		$I->click('Configure device');
		$I->waitForText('Config & State data', 10);
		$I->canSeeInCurrentUrl('sections/0/interfaces/');
	}

	public function _changeTo(WebGuy $I, $datastore = 'Candidate') {
		$I->selectOption('#form_source', $datastore);
		$I->expectTo('change datastore to '.$datastore);
		$I->waitForElementNotVisible('#ajax-spinner');
		$I->waitForText('Config data only');
	}

	public function _createEmptyModule(WebGuy $I) {
		$I->canSee('Create empty root element');
		$I->wait(2);
		$I->click('.typeaheadName');
		$I->waitForElement('.typeahead');
		$I->click('interfaces');
		$I->click('.typeaheadNS');
		$I->expectTo('see only one available NS');
		$I->seeNumberOfElements('.typeaheadNS + .typeahead a', 1);
		$I->click('urn:ietf:params:xml:ns:yang:ietf-interfaces');
		$I->click('Create');
		$I->waitForText('interfaces');
	}

	public function testDatastore(WebGuy $I) {
		$this->_login($I);
		$this->_changeTo($I);
		$I->wantTo('test candidate datastore');

		// check if module is empty
		if (0) {
			$I->click('.remove-child');
			$I->click('Delete record');
			$I->waitForElementNotVisible('#ajax-spinner');
			$I->waitForText('Create empty root element');
			CommonScenarios::checkNumberOfFlashes($I, 1);
		}

		$this->_createEmptyModule($I);
	}

	public function _copyToRunning(WebGuy $I) {
		$this->_login($I);
		$this->_changeTo($I, 'Start-up');
		$I->selectOption("#form_target", 'Running');
		$I->click('Copy active datastore');
		$I->waitForElementNotVisible('#ajax-spinner');

		$I->expectTo('see copied candidate datastore');
		$I->selectOption('#form_source', 'Running');
		$I->waitForElementNotVisible('#ajax-spinner');
		$I->canSee('interfaces');
		$I->seeNumberOfElements('.leaf-line', 1);
	}
}