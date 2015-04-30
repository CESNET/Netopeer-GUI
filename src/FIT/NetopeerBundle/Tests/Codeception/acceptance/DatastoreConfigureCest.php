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
		$I->click('h5');
		$I->expectTo('change datastore to '.$datastore);
		$I->waitForText('Config data only');
	}

	public function _createEmptyModule(WebGuy $I) {
		$I->see('Create empty root element');
		$I->click('.typeaheadName');
		$I->waitForElement('.typeahead');
		$I->click('interfaces');
		$I->click('typeaheadNS');
		$I->expectTo('see only one available NS');
		$I->seeNumberOfElements('.typeahead a', 1);
		$I->click('.typeahead a');
		$I->click('Create');
		$I->waitForText('interfaces');
	}

	public function testDatastore(WebGuy $I) {
		$this->_login($I);
		$this->_changeTo($I);
		$I->wantTo('test candidate datastore');

		// check if module is empty
//		$text = $I->grabTextFrom('h2');
		// TODO
		if (0 && $text != 'Create empty root element') {
			$I->click('.remove-child');
			$I->click('Delete record');
			$I->waitForElementNotVisible('#ajax-spinner');
			$I->waitForText('Create empty root element');
			$I->seeNumberOfElements('.message.success', 1);
		}

		$this->_createEmptyModule($I);
	}

	/**
	 * @before testDatastore
	 */
	public function copyToRunning(WebGuy $I) {
		$I->selectOption("#form_target", 'Running');
		$I->click('Copy active datastore');
	}
}