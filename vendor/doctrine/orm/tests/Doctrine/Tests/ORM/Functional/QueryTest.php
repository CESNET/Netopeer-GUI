<?php

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\DBAL\Connection;
use Doctrine\Tests\Models\CMS\CmsUser,
    Doctrine\Tests\Models\CMS\CmsArticle,
    Doctrine\Tests\Models\CMS\CmsPhonenumber;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parameter;

require_once __DIR__ . '/../../TestInit.php';

/**
 * Functional Query tests.
 *
 * @author robo
 */
class QueryTest extends \Doctrine\Tests\OrmFunctionalTestCase
{
    protected function setUp()
    {
        $this->useModelSet('cms');
        parent::setUp();
    }

    public function testSimpleQueries()
    {
        $user = new CmsUser;
        $user->name = 'Guilherme';
        $user->username = 'gblanco';
        $user->status = 'developer';
        $this->_em->persist($user);
        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("select u, upper(u.name) from Doctrine\Tests\Models\CMS\CmsUser u where u.username = 'gblanco'");

        $result = $query->getResult();

        $this->assertEquals(1, count($result));
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $result[0][0]);
        $this->assertEquals('Guilherme', $result[0][0]->name);
        $this->assertEquals('gblanco', $result[0][0]->username);
        $this->assertEquals('developer', $result[0][0]->status);
        $this->assertEquals('GUILHERME', $result[0][1]);

        $resultArray = $query->getArrayResult();
        $this->assertEquals(1, count($resultArray));
        $this->assertTrue(is_array($resultArray[0][0]));
        $this->assertEquals('Guilherme', $resultArray[0][0]['name']);
        $this->assertEquals('gblanco', $resultArray[0][0]['username']);
        $this->assertEquals('developer', $resultArray[0][0]['status']);
        $this->assertEquals('GUILHERME', $resultArray[0][1]);

        $scalarResult = $query->getScalarResult();
        $this->assertEquals(1, count($scalarResult));
        $this->assertEquals('Guilherme', $scalarResult[0]['u_name']);
        $this->assertEquals('gblanco', $scalarResult[0]['u_username']);
        $this->assertEquals('developer', $scalarResult[0]['u_status']);
        $this->assertEquals('GUILHERME', $scalarResult[0][1]);

        $query = $this->_em->createQuery("select upper(u.name) from Doctrine\Tests\Models\CMS\CmsUser u where u.username = 'gblanco'");
        $this->assertEquals('GUILHERME', $query->getSingleScalarResult());
    }

    public function testJoinQueries()
    {
        $user = new CmsUser;
        $user->name = 'Guilherme';
        $user->username = 'gblanco';
        $user->status = 'developer';

        $article1 = new CmsArticle;
        $article1->topic = "Doctrine 2";
        $article1->text = "This is an introduction to Doctrine 2.";
        $user->addArticle($article1);

        $article2 = new CmsArticle;
        $article2->topic = "Symfony 2";
        $article2->text = "This is an introduction to Symfony 2.";
        $user->addArticle($article2);

        $this->_em->persist($user);
        $this->_em->persist($article1);
        $this->_em->persist($article2);

        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("select u, a from Doctrine\Tests\Models\CMS\CmsUser u join u.articles a ORDER BY a.topic");
        $users = $query->getResult();
        $this->assertEquals(1, count($users));
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $users[0]);
        $this->assertEquals(2, count($users[0]->articles));
        $this->assertEquals('Doctrine 2', $users[0]->articles[0]->topic);
        $this->assertEquals('Symfony 2', $users[0]->articles[1]->topic);
    }

    public function testUsingZeroBasedQueryParameterShouldWork()
    {
        $user = new CmsUser;
        $user->name = 'Jonathan';
        $user->username = 'jwage';
        $user->status = 'developer';
        $this->_em->persist($user);
        $this->_em->flush();
        $this->_em->clear();

        $q = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.username = ?0');
        $q->setParameter(0, 'jwage');
        $user = $q->getSingleResult();

        $this->assertNotNull($user);
    }

    public function testUsingUnknownQueryParameterShouldThrowException()
    {
        $this->setExpectedException(
            "Doctrine\ORM\Query\QueryException",
            "Invalid parameter: token 2 is not defined in the query."
        );

        $q = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.name = ?1');
        $q->setParameter(2, 'jwage');
        $user = $q->getSingleResult();
    }

    public function testMismatchingParamExpectedParamCount()
    {
        $this->setExpectedException(
            "Doctrine\ORM\Query\QueryException",
            "Invalid parameter number: number of bound variables does not match number of tokens"
        );

        $q = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.name = ?1');
        $q->setParameter(1, 'jwage');
        $q->setParameter(2, 'jwage');

        $user = $q->getSingleResult();
    }

    public function testInvalidInputParameterThrowsException()
    {
        $this->setExpectedException("Doctrine\ORM\Query\QueryException");

        $q = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.name = ?');
        $q->setParameter(1, 'jwage');
        $user = $q->getSingleResult();
    }

    public function testSetParameters()
    {
        $q = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.name = ?1 AND u.status = ?2');

        $parameters = new ArrayCollection();
        $parameters->add(new Parameter(1, 'jwage'));
        $parameters->add(new Parameter(2, 'active'));

        $q->setParameters($parameters);
        $users = $q->getResult();
    }

    public function testSetParametersBackwardsCompatible()
    {
        $q = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.name = ?1 AND u.status = ?2');
        $q->setParameters(array(1 => 'jwage', 2 => 'active'));
        
        $users = $q->getResult();
    }

    /**
     * @group DDC-1070
     */
    public function testIterateResultAsArrayAndParams()
    {
        $article1 = new CmsArticle;
        $article1->topic = "Doctrine 2";
        $article1->text = "This is an introduction to Doctrine 2.";

        $article2 = new CmsArticle;
        $article2->topic = "Symfony 2";
        $article2->text = "This is an introduction to Symfony 2.";

        $this->_em->persist($article1);
        $this->_em->persist($article2);

        $this->_em->flush();
        $this->_em->clear();
        $articleId = $article1->id;

        $query = $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a WHERE a.topic = ?1");
        $articles = $query->iterate(new ArrayCollection(array(new Parameter(1, 'Doctrine 2'))), Query::HYDRATE_ARRAY);

        $found = array();
        foreach ($articles AS $article) {
            $found[] = $article;
        }
        $this->assertEquals(1, count($found));
        $this->assertEquals(array(
            array(array('id' => $articleId, 'topic' => 'Doctrine 2', 'text' => 'This is an introduction to Doctrine 2.', 'version' => 1))
        ), $found);
    }

    public function testIterateResult_IterativelyBuildUpUnitOfWork()
    {
        $article1 = new CmsArticle;
        $article1->topic = "Doctrine 2";
        $article1->text = "This is an introduction to Doctrine 2.";

        $article2 = new CmsArticle;
        $article2->topic = "Symfony 2";
        $article2->text = "This is an introduction to Symfony 2.";

        $this->_em->persist($article1);
        $this->_em->persist($article2);

        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a");
        $articles = $query->iterate();

        $iteratedCount = 0;
        $topics = array();
        foreach($articles AS $row) {
            $article = $row[0];
            $topics[] = $article->topic;

            $identityMap = $this->_em->getUnitOfWork()->getIdentityMap();
            $identityMapCount = count($identityMap['Doctrine\Tests\Models\CMS\CmsArticle']);
            $this->assertTrue($identityMapCount>$iteratedCount);

            $iteratedCount++;
        }

        $this->assertEquals(array("Doctrine 2", "Symfony 2"), $topics);
        $this->assertEquals(2, $iteratedCount);

        $this->_em->flush();
        $this->_em->clear();
    }

    public function testIterateResultClearEveryCycle()
    {
        $article1 = new CmsArticle;
        $article1->topic = "Doctrine 2";
        $article1->text = "This is an introduction to Doctrine 2.";

        $article2 = new CmsArticle;
        $article2->topic = "Symfony 2";
        $article2->text = "This is an introduction to Symfony 2.";

        $this->_em->persist($article1);
        $this->_em->persist($article2);

        $this->_em->flush();
        $this->_em->clear();

        $query    = $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a");
        $articles = $query->iterate();

        $iteratedCount = 0;
        $topics = array();
        foreach($articles AS $row) {
            $article  = $row[0];
            $topics[] = $article->topic;

            $this->_em->clear();

            $iteratedCount++;
        }

        $this->assertEquals(array("Doctrine 2", "Symfony 2"), $topics);
        $this->assertEquals(2, $iteratedCount);

        $this->_em->flush();
    }

    /**
     * @expectedException \Doctrine\ORM\Query\QueryException
     */
    public function testIterateResult_FetchJoinedCollection_ThrowsException()
    {
        $query = $this->_em->createQuery("SELECT u, a FROM Doctrine\Tests\Models\CMS\CmsUser u JOIN u.articles a");
        $articles = $query->iterate();
    }

    /**
     * @expectedException Doctrine\ORM\NoResultException
     */
    public function testGetSingleResultThrowsExceptionOnNoResult()
    {
        $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a")
             ->getSingleResult();
    }

    /**
     * @expectedException Doctrine\ORM\NoResultException
     */
    public function testGetSingleScalarResultThrowsExceptionOnNoResult()
    {
        $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a")
             ->getSingleScalarResult();
    }

    /**
     * @expectedException Doctrine\ORM\NonUniqueResultException
     */
    public function testGetSingleScalarResultThrowsExceptionOnNonUniqueResult()
    {
        $user = new CmsUser;
        $user->name = 'Guilherme';
        $user->username = 'gblanco';
        $user->status = 'developer';

        $article1 = new CmsArticle;
        $article1->topic = "Doctrine 2";
        $article1->text = "This is an introduction to Doctrine 2.";
        $user->addArticle($article1);

        $article2 = new CmsArticle;
        $article2->topic = "Symfony 2";
        $article2->text = "This is an introduction to Symfony 2.";
        $user->addArticle($article2);

        $this->_em->persist($user);
        $this->_em->persist($article1);
        $this->_em->persist($article2);

        $this->_em->flush();
        $this->_em->clear();

        $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a")
             ->getSingleScalarResult();
    }

    public function testModifiedLimitQuery()
    {
        for ($i = 0; $i < 5; $i++) {
            $user = new CmsUser;
            $user->name = 'Guilherme' . $i;
            $user->username = 'gblanco' . $i;
            $user->status = 'developer';
            $this->_em->persist($user);
        }

        $this->_em->flush();
        $this->_em->clear();

        $data = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u')
                  ->setFirstResult(1)
                  ->setMaxResults(2)
                  ->getResult();

        $this->assertEquals(2, count($data));
        $this->assertEquals('gblanco1', $data[0]->username);
        $this->assertEquals('gblanco2', $data[1]->username);

        $data = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u')
                  ->setFirstResult(3)
                  ->setMaxResults(2)
                  ->getResult();

        $this->assertEquals(2, count($data));
        $this->assertEquals('gblanco3', $data[0]->username);
        $this->assertEquals('gblanco4', $data[1]->username);

        $data = $this->_em->createQuery('SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u')
                  ->setFirstResult(3)
                  ->setMaxResults(2)
                  ->getScalarResult();
    }

    public function testSupportsQueriesWithEntityNamespaces()
    {
        $this->_em->getConfiguration()->addEntityNamespace('CMS', 'Doctrine\Tests\Models\CMS');

        try {
            $query = $this->_em->createQuery('UPDATE CMS:CmsUser u SET u.name = ?1');
            $this->assertEquals('UPDATE cms_users SET name = ?', $query->getSql());
            $query->free();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->_em->getConfiguration()->setEntityNamespaces(array());
    }

    /**
     * @group DDC-604
     */
    public function testEntityParameters()
    {
        $article = new CmsArticle;
        $article->topic = "dr. dolittle";
        $article->text = "Once upon a time ...";
        $author = new CmsUser;
        $author->name = "anonymous";
        $author->username = "anon";
        $author->status = "here";
        $article->user = $author;
        $this->_em->persist($author);
        $this->_em->persist($article);
        $this->_em->flush();
        $this->_em->clear();
        //$this->_em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);
        $q = $this->_em->createQuery("select a from Doctrine\Tests\Models\CMS\CmsArticle a where a.topic = :topic and a.user = :user")
                ->setParameter("user", $this->_em->getReference('Doctrine\Tests\Models\CMS\CmsUser', $author->id))
                ->setParameter("topic", "dr. dolittle");

        $result = $q->getResult();
        $this->assertEquals(1, count($result));
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsArticle', $result[0]);
        $this->assertEquals("dr. dolittle", $result[0]->topic);
        $this->assertInstanceOf('Doctrine\ORM\Proxy\Proxy', $result[0]->user);
        $this->assertFalse($result[0]->user->__isInitialized__);
    }

    /**
     * @group DDC-952
     */
    public function testEnableFetchEagerMode()
    {
        for ($i = 0; $i < 10; $i++) {
            $article = new CmsArticle;
            $article->topic = "dr. dolittle";
            $article->text = "Once upon a time ...";
            $author = new CmsUser;
            $author->name = "anonymous";
            $author->username = "anon".$i;
            $author->status = "here";
            $article->user = $author;
            $this->_em->persist($author);
            $this->_em->persist($article);
        }
        $this->_em->flush();
        $this->_em->clear();

        $articles = $this->_em->createQuery('select a from Doctrine\Tests\Models\CMS\CmsArticle a')
                         ->setFetchMode('Doctrine\Tests\Models\CMS\CmsArticle', 'user', ClassMetadata::FETCH_EAGER)
                         ->getResult();

        $this->assertEquals(10, count($articles));
        foreach ($articles AS $article) {
            $this->assertNotInstanceOf('Doctrine\ORM\Proxy\Proxy', $article);
        }
    }

    /**
     * @group DDC-991
     */
    public function testgetOneOrNullResult()
    {
        $user = new CmsUser;
        $user->name = 'Guilherme';
        $user->username = 'gblanco';
        $user->status = 'developer';
        $this->_em->persist($user);
        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("select u from Doctrine\Tests\Models\CMS\CmsUser u where u.username = 'gblanco'");

        $fetchedUser = $query->getOneOrNullResult();
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $fetchedUser);
        $this->assertEquals('gblanco', $fetchedUser->username);

        $query = $this->_em->createQuery("select u.username from Doctrine\Tests\Models\CMS\CmsUser u where u.username = 'gblanco'");
        $fetchedUsername = $query->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);
        $this->assertEquals('gblanco', $fetchedUsername);
    }

    /**
     * @group DDC-991
     */
    public function testgetOneOrNullResultSeveralRows()
    {
        $user = new CmsUser;
        $user->name = 'Guilherme';
        $user->username = 'gblanco';
        $user->status = 'developer';
        $this->_em->persist($user);
        $user = new CmsUser;
        $user->name = 'Roman';
        $user->username = 'romanb';
        $user->status = 'developer';
        $this->_em->persist($user);
        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("select u from Doctrine\Tests\Models\CMS\CmsUser u");

        $this->setExpectedException('Doctrine\ORM\NonUniqueResultException');
        $fetchedUser = $query->getOneOrNullResult();
    }

    /**
     * @group DDC-991
     */
    public function testgetOneOrNullResultNoRows()
    {
        $query = $this->_em->createQuery("select u from Doctrine\Tests\Models\CMS\CmsUser u");
        $this->assertNull($query->getOneOrNullResult());

        $query = $this->_em->createQuery("select u.username from Doctrine\Tests\Models\CMS\CmsUser u where u.username = 'gblanco'");
        $this->assertNull($query->getOneOrNullResult(Query::HYDRATE_SCALAR));
    }

    /**
     * @group DBAL-171
     */
    public function testParameterOrder()
    {
        $user1 = new CmsUser;
        $user1->name = 'Benjamin';
        $user1->username = 'beberlei';
        $user1->status = 'developer';
        $this->_em->persist($user1);

        $user2 = new CmsUser;
        $user2->name = 'Roman';
        $user2->username = 'romanb';
        $user2->status = 'developer';
        $this->_em->persist($user2);

        $user3 = new CmsUser;
        $user3->name = 'Jonathan';
        $user3->username = 'jwage';
        $user3->status = 'developer';
        $this->_em->persist($user3);

        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.status = :a AND u.id IN (:b)");
        $query->setParameters(new ArrayCollection(array(
            new Parameter('b', array($user1->id, $user2->id, $user3->id)),
            new Parameter('a', 'developer')
        )));
        $result = $query->getResult();

        $this->assertEquals(3, count($result));
    }

    public function testDqlWithAutoInferOfParameters()
    {
        $user = new CmsUser;
        $user->name = 'Benjamin';
        $user->username = 'beberlei';
        $user->status = 'developer';
        $this->_em->persist($user);

        $user = new CmsUser;
        $user->name = 'Roman';
        $user->username = 'romanb';
        $user->status = 'developer';
        $this->_em->persist($user);

        $user = new CmsUser;
        $user->name = 'Jonathan';
        $user->username = 'jwage';
        $user->status = 'developer';
        $this->_em->persist($user);

        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u.username IN (?0)");
        $query->setParameter(0, array('beberlei', 'jwage'));

        $users = $query->execute();

        $this->assertEquals(2, count($users));
    }

    public function testQueryBuilderWithStringWhereClauseContainingOrAndConditionalPrimary()
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('u')
           ->from('Doctrine\Tests\Models\CMS\CmsUser', 'u')
           ->innerJoin('u.articles', 'a')
           ->where('(u.id = 0) OR (u.id IS NULL)');

        $query = $qb->getQuery();
        $users = $query->execute();

        $this->assertEquals(0, count($users));
    }

    public function testQueryWithArrayOfEntitiesAsParameter()
    {
        $userA = new CmsUser;
        $userA->name = 'Benjamin';
        $userA->username = 'beberlei';
        $userA->status = 'developer';
        $this->_em->persist($userA);

        $userB = new CmsUser;
        $userB->name = 'Roman';
        $userB->username = 'romanb';
        $userB->status = 'developer';
        $this->_em->persist($userB);

        $userC = new CmsUser;
        $userC->name = 'Jonathan';
        $userC->username = 'jwage';
        $userC->status = 'developer';
        $this->_em->persist($userC);

        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u WHERE u IN (?0) OR u.username = ?1");
        $query->setParameter(0, array($userA, $userC));
        $query->setParameter(1, 'beberlei');

        $users = $query->execute();

        $this->assertEquals(2, count($users));
    }

    public function testQueryWithHiddenAsSelectExpression()
    {
        $userA = new CmsUser;
        $userA->name = 'Benjamin';
        $userA->username = 'beberlei';
        $userA->status = 'developer';
        $this->_em->persist($userA);

        $userB = new CmsUser;
        $userB->name = 'Roman';
        $userB->username = 'romanb';
        $userB->status = 'developer';
        $this->_em->persist($userB);

        $userC = new CmsUser;
        $userC->name = 'Jonathan';
        $userC->username = 'jwage';
        $userC->status = 'developer';
        $this->_em->persist($userC);

        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("SELECT u, (SELECT COUNT(u2.id) FROM Doctrine\Tests\Models\CMS\CmsUser u2) AS HIDDEN total FROM Doctrine\Tests\Models\CMS\CmsUser u");
        $users = $query->execute();

        $this->assertEquals(3, count($users));
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $users[0]);
    }

    /**
     * @group DDC-1651
     */
    public function testSetParameterBindingSingleIdentifierObject()
    {
        $userC = new CmsUser;
        $userC->name = 'Jonathan';
        $userC->username = 'jwage';
        $userC->status = 'developer';
        $this->_em->persist($userC);

        $this->_em->flush();
        $this->_em->clear();

        $q = $this->_em->createQuery("SELECT DISTINCT u from Doctrine\Tests\Models\CMS\CmsUser u WHERE u.id = ?1");
        $q->setParameter(1, $userC);

        $this->assertEquals($userC, $q->getParameter(1)->getValue());

        // Parameter is not converted before, but it should be converted during execution. Test should not fail here
        $q->getResult();
    }


    /**
     * @group DDC-1822
     */
    public function testUnexpectedResultException()
    {
        $dql            = "SELECT u FROM Doctrine\Tests\Models\CMS\CmsUser u";
        $u1             = new CmsUser;
        $u2             = new CmsUser;
        $u1->name       = 'Fabio B. Silva';
        $u1->username   = 'FabioBatSilva';
        $u1->status     = 'developer';
        $u2->name       = 'Test';
        $u2->username   = 'test';
        $u2->status     = 'tester';

        try {
            $this->_em->createQuery($dql)->getSingleResult();
            $this->fail('Expected exception "\Doctrine\ORM\NoResultException".');
        } catch (\Doctrine\ORM\UnexpectedResultException $exc) {
            $this->assertInstanceOf('\Doctrine\ORM\NoResultException', $exc);
        }


        $this->_em->persist($u1);
        $this->_em->persist($u2);
        $this->_em->flush();
        $this->_em->clear();

        try {
            $this->_em->createQuery($dql)->getSingleResult();
            $this->fail('Expected exception "\Doctrine\ORM\NonUniqueResultException".');
        } catch (\Doctrine\ORM\UnexpectedResultException $exc) {
            $this->assertInstanceOf('\Doctrine\ORM\NonUniqueResultException', $exc);
        }
    }

    public function testMultipleJoinComponentsUsingInnerJoin()
    {
        $userA = new CmsUser;
        $userA->name = 'Benjamin';
        $userA->username = 'beberlei';
        $userA->status = 'developer';

        $phonenumberA = new CmsPhonenumber;
        $phonenumberA->phonenumber = '111111';
        $userA->addPhonenumber($phonenumberA);

        $userB = new CmsUser;
        $userB->name = 'Alexander';
        $userB->username = 'asm89';
        $userB->status = 'developer';

        $this->_em->persist($userA);
        $this->_em->persist($userB);
        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("
            SELECT u, p
              FROM Doctrine\Tests\Models\CMS\CmsUser u
             INNER JOIN Doctrine\Tests\Models\CMS\CmsPhonenumber p WITH u = p.user
        ");
        $users = $query->execute();

        $this->assertEquals(2, count($users));
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $users[0]);
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsPhonenumber', $users[1]);
    }

    public function testMultipleJoinComponentsUsingLeftJoin()
    {
        $userA = new CmsUser;
        $userA->name = 'Benjamin';
        $userA->username = 'beberlei';
        $userA->status = 'developer';

        $phonenumberA = new CmsPhonenumber;
        $phonenumberA->phonenumber = '111111';
        $userA->addPhonenumber($phonenumberA);

        $userB = new CmsUser;
        $userB->name = 'Alexander';
        $userB->username = 'asm89';
        $userB->status = 'developer';

        $this->_em->persist($userA);
        $this->_em->persist($userB);
        $this->_em->flush();
        $this->_em->clear();

        $query = $this->_em->createQuery("
            SELECT u, p
              FROM Doctrine\Tests\Models\CMS\CmsUser u
              LEFT JOIN Doctrine\Tests\Models\CMS\CmsPhonenumber p WITH u = p.user
        ");
        $users = $query->execute();

        $this->assertEquals(4, count($users));
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $users[0]);
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsPhonenumber', $users[1]);
        $this->assertInstanceOf('Doctrine\Tests\Models\CMS\CmsUser', $users[2]);
        $this->assertNull($users[3]);
    }
}
