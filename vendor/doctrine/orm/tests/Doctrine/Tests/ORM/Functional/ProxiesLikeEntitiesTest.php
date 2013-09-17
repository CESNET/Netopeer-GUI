<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Common\Util\ClassUtils,
    Doctrine\Tests\Models\CMS\CmsUser,
    Doctrine\Tests\Proxies\__CG__\Doctrine\Tests\Models\CMS\CmsUser as Proxy;

/**
 * Test that Doctrine ORM correctly works with proxy instances exactly like with ordinary Entities
 *
 * The test considers two possible cases:
 *  a) __initialized__ = true and no identifier set in proxy
 *  b) __initialized__ = false and identifier set in proxy and in property
 * @todo All other cases would cause lazy loading issues
 */
class ProxiesLikeEntitiesTest extends \Doctrine\Tests\OrmFunctionalTestCase
{
    /**
     * @var CmsUser
     */
    protected $user;

    protected function setUp()
    {
        parent::setUp();
        try {
            $this->_schemaTool->createSchema(array(
                $this->_em->getClassMetadata('Doctrine\Tests\Models\CMS\CmsUser'),
            ));
        } catch (\Exception $e) {
        }
        $this->user = new CmsUser();
        $this->user->username = 'ocramius';
        $this->user->name = 'Marco';
        $this->_em->persist($this->user);
        $this->_em->flush();
        $this->_em->clear();
    }

    /**
     * Verifies that a proxy can be successfully persisted and updated
     */
    public function testPersistUpdate()
    {
        // Considering case (a)
        $persister = $this->_em->getUnitOfWork()->getEntityPersister('Doctrine\Tests\Models\CMS\CmsUser');
        $proxy = new Proxy($persister, array());
        $proxy->__isInitialized__ = true;
        $proxy->username = 'ocra';
        $proxy->name = 'Marco';
        $this->_em->persist($proxy);
        $this->_em->flush();
        $this->assertNotNull($proxy->getId());
        $proxy->name = 'Marco Pivetta';
        $this
            ->_em
            ->getUnitOfWork()
            ->computeChangeSet($this->_em->getClassMetadata('Doctrine\Tests\Models\CMS\CmsUser'), $proxy);
        $this->assertNotEmpty($this->_em->getUnitOfWork()->getEntityChangeSet($proxy));
        $this->assertEquals('Marco Pivetta', $this->_em->find('Doctrine\Tests\Models\CMS\CmsUser', $proxy->getId())->name);
        $this->_em->remove($proxy);
        $this->_em->flush();
    }

    public function testEntityWithIdentifier()
    {
        // Considering case (b)
        $persister = $this->_em->getUnitOfWork()->getEntityPersister('Doctrine\Tests\Models\CMS\CmsUser');
        $uninitializedProxy = new Proxy($persister, array('id' => $this->user->getId()));
        $uninitializedProxy->id = $this->user->getId();
        $uninitializedProxy->username = 'ocra';
        $uninitializedProxy->name = 'Marco Pivetta';
        $this->_em->persist($uninitializedProxy);
        $this->_em->flush();
        $this->assertEquals($this->user->getId(), $uninitializedProxy->getId());
        $this->_em->remove($uninitializedProxy);
        $this->_em->flush();
    }

    /**
     * Verifying that proxies can be used without problems as query parameters
     */
    public function testProxyAsDqlParameterPersist()
    {
        $persister = $this->_em->getUnitOfWork()->getEntityPersister('Doctrine\Tests\Models\CMS\CmsUser');
        $proxy = new Proxy($persister, array('id' => $this->user->getId()));
        $proxy->id = $this->user->getId();
        $result = $this
            ->_em
            ->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u = ?1')
            ->setParameter(1, $proxy)
            ->getSingleResult();
        $this->assertSame($this->user->getId(), $result->getId());
        $this->_em->remove($proxy);
        $this->_em->flush();
    }

    /**
     * Verifying that proxies can be used without problems as query parameters
     */
    public function testFindWithProxyName()
    {
        $result = $this
            ->_em
            ->find('Doctrine\Tests\Proxies\__CG__\Doctrine\Tests\Models\CMS\CmsUser', $this->user->getId());
        $this->assertSame($this->user->getId(), $result->getId());
        $this->_em->clear();
        $result = $this
            ->_em
            ->getReference('Doctrine\Tests\Proxies\__CG__\Doctrine\Tests\Models\CMS\CmsUser', $this->user->getId());
        $this->assertSame($this->user->getId(), $result->getId());
        $this->_em->clear();
        $result = $this
            ->_em
            ->getRepository('Doctrine\Tests\Proxies\__CG__\Doctrine\Tests\Models\CMS\CmsUser')
            ->findOneBy(array('username' => $this->user->username));
        $this->assertSame($this->user->getId(), $result->getId());
        $this->_em->clear();
        $result = $this
            ->_em
            ->createQuery('SELECT u FROM Doctrine\Tests\Proxies\__CG__\Doctrine\Tests\Models\CMS\CmsUser u WHERE u.id = ?1')
            ->setParameter(1, $this->user->getId())
            ->getSingleResult();
        $this->assertSame($this->user->getId(), $result->getId());
        $this->_em->clear();
    }

    protected function tearDown()
    {
        $this->_em->createQuery('DELETE FROM Doctrine\Tests\Models\CMS\CmsUser u')->execute();
    }
}