<?php

use FIT\NetopeerBundle\Tests\Codeception\_support\CommonScenarios;

class TuringConfigureCest
{

	public function _before(WebGuy $I)
	{
		CommonScenarios::login($I);
		CommonScenarios::connectToLocalhostDevice($I);
		$I->click('Configure device');
		$I->waitForText('Config & State data', 10);
		$I->canSeeInCurrentUrl('sections/0/interfaces/');
		$I->amOnPage('/sections/0/turing-machine/');
		$I->waitForText('turing-machine', 10);
	}

	public function _after(WebGuy $I)
	{
		$I->amOnPage('/logout');
	}

	public function _turingAddTransition(WebGuy $I, $addDelta = false, $state = 0) {
		$I->wait(2);
		if ($addDelta === false) {
			$I->click('.create-child');
			$I->waitForText('transition-function');
			$I->click('transition-function');
			$I->wait(2);
			$I->click('.create-child[rel*="--*?1!--*?1!"]', '.generatedForm');
			$prefix = '--*?1!';
		} else {
			$I->click('.create-child[rel*="--*--*?1!"]');
			$prefix = '--*';
		}

		CommonScenarios::waitAndClickInTypeahead($I, 'delta');
		$I->fillField('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?1!"]', 'test-name'.time());

		// add input subtree
		$I->click('.create-child[rel*="'.$prefix.'--*?1!--*?1!"]', '.generatedForm');
		CommonScenarios::waitAndClickInTypeahead($I, 'input');
		$I->waitForElement('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?2!--*?1!"]');
		$I->fillField('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?2!--*?1!"]', $state);
		$I->fillField('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?2!--*?2!"]', '1');

		// add output subtree
		$I->click('.create-child[rel*="'.$prefix.'--*?1!--*?1!"]', '.generatedForm');
		CommonScenarios::waitAndClickInTypeahead($I, 'output');

		$I->click('.create-child[rel*="'.$prefix.'--*?1!--*?1!--*?3!"]', '.generatedForm');
		CommonScenarios::waitAndClickInTypeahead($I, 'state');
		$I->waitForElement('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?3!--*?1!"]');
		$I->fillField('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?3!--*?1!"]', ($state + 1));

		$I->click('.create-child[rel*="'.$prefix.'--*?1!--*?1!--*?3!"]', '.generatedForm');
		CommonScenarios::waitAndClickInTypeahead($I, 'symbol');
		$I->waitForElement('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?3!--*?2!"]');
		$I->fillField('.generatedForm input.value[name*="'.$prefix.'--*?1!--*?1!--*?3!--*?2!"]', '2');

		$I->click('.create-child[rel*="'.$prefix.'--*?1!--*?1!--*?3!"]', '.generatedForm');
		CommonScenarios::waitAndClickInTypeahead($I, 'head-move');
		$I->waitForElement('.generatedForm select[name*="'.$prefix.'--*?1!--*?1!--*?3!--*?3!"]');
		$I->selectOption('.generatedForm select[name*="'.$prefix.'--*?1!--*?1!--*?3!--*?3!"]', 'right');
	}

	public function testEditConfig(WebGuy $I) {
		$I->wantTo('create new transition function using submit button');

		$this->_turingAddTransition($I);
		$I->click('Create new node');
		$I->waitForElementNotVisible('#ajax-spinner');
		$I->wait(2);

		// see result
		CommonScenarios::checkNumberOfFlashes($I, 1);
		$I->canSeeNumberOfElements('.level-1.delta', 1);
	}

	public function testEditConfigWithCommit(WebGuy $I) {
		$I->wantTo('create new transition function using commit all');

		$this->_turingAddTransition($I, true, 2);
		$I->click('Append changes');

		$I->seeNumberOfElements('form.addedForm', 1);

		$I->click('Commit all changes');
		$I->waitForElementNotVisible('#ajax-spinner');

		$I->wait(2);

		// see result
		CommonScenarios::checkNumberOfFlashes($I, 1);
		$I->canSeeNumberOfElements('.level-1.delta', 2);
	}
}