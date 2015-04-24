<?php
$I = new WebGuy($scenario);
$I->wantTo('log in as admin user');
$I->amOnPage('/login');
$I->fillField('Username','admin');
$I->fillField('Password','pass');
$I->click('Log in');
$I->see('List of active connections');
