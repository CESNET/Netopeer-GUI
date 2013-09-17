<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Core\Encoder;

use Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder;

class Pbkdf2PasswordEncoderTest extends \PHPUnit_Framework_TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new Pbkdf2PasswordEncoder('sha256', false, 1, 40);

        $this->assertTrue($encoder->isPasswordValid('c1232f10f62715fda06ae7c0a2037ca19b33cf103b727ba56d870c11f290a2ab106974c75607c8a3', 'password', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new Pbkdf2PasswordEncoder('sha256', false, 1, 40);
        $this->assertSame('c1232f10f62715fda06ae7c0a2037ca19b33cf103b727ba56d870c11f290a2ab106974c75607c8a3', $encoder->encodePassword('password', ''));

        $encoder = new Pbkdf2PasswordEncoder('sha256', true, 1, 40);
        $this->assertSame('wSMvEPYnFf2gaufAogN8oZszzxA7cnulbYcMEfKQoqsQaXTHVgfIow==', $encoder->encodePassword('password', ''));

        $encoder = new Pbkdf2PasswordEncoder('sha256', false, 2, 40);
        $this->assertSame('8bc2f9167a81cdcfad1235cd9047f1136271c1f978fcfcb35e22dbeafa4634f6fd2214218ed63ebb', $encoder->encodePassword('password', ''));
    }

    /**
     * @expectedException LogicException
     */
    public function testEncodePasswordAlgorithmDoesNotExist()
    {
        $encoder = new Pbkdf2PasswordEncoder('foobar');
        $encoder->encodePassword('password', '');
    }
}
