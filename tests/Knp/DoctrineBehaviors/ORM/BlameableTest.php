<?php

namespace Tests\Knp\DoctrineBehaviors\ORM;

use Doctrine\Common\EventManager;

require_once 'EntityManagerProvider.php';

class BlameableTest extends \PHPUnit_Framework_TestCase
{
    private $listener;

    use EntityManagerProvider;

    protected function getUsedEntityFixtures()
    {
        return [
            'BehaviorFixtures\\ORM\\BlameableEntity',
            'BehaviorFixtures\\ORM\\UserEntity'
        ];
    }

    protected function getEventManager($user = null, $userCallback = null, $userEntity = null)
    {
        $em = new EventManager;

        $this->listener = new \Knp\DoctrineBehaviors\ORM\Blameable\BlameableListener(
            $userCallback,
            $userEntity
        );
        $this->listener->setUser($user);

        $em->addEventSubscriber($this->listener);

        return $em;
    }

    public function testCreate()
    {
        $em = $this->getEntityManager($this->getEventManager('user'));

        $entity = new \BehaviorFixtures\ORM\BlameableEntity();

        $em->persist($entity);
        $em->flush();

        $this->assertEquals('user', $entity->getCreatedBy());
        $this->assertEquals('user', $entity->getUpdatedBy());
    }

    public function testUpdate()
    {
        $em = $this->getEntityManager($this->getEventManager('user'));

        $entity = new \BehaviorFixtures\ORM\BlameableEntity();

        $em->persist($entity);
        $em->flush();
        $id = $entity->getId();
        $createdBy = $entity->getCreatedBy();
        $em->clear();

        $listeners = $em->getEventManager()->getListeners()['preUpdate'];
        $listener = array_pop($listeners);
        $listener->setUser('user2');

        $entity = $em->getRepository('BehaviorFixtures\ORM\BlameableEntity')->find($id);
        $entity->setTitle('test'); // need to modify at least one column to trigger onUpdate
        $em->flush();
        $em->clear();

        //$entity = $em->getRepository('BehaviorFixtures\ORM\BlameableEntity')->find($id);
        $this->assertEquals($createdBy, $entity->getCreatedBy(), 'createdBy is constant');
        $this->assertEquals('user2', $entity->getUpdatedBy());

        $this->assertNotEquals(
            $entity->getCreatedBy(),
            $entity->getUpdatedBy(),
            'createBy and updatedBy have diverged since new update'
        );
    }

    public function testListenerWithUserCallback()
    {
        $user = new \BehaviorFixtures\ORM\UserEntity();
        $user->setUsername('user');

        $user2 = new \BehaviorFixtures\ORM\UserEntity();
        $user2->setUsername('user2');

        $userCallback = function() use($user) {
            return $user;
        };

        $em = $this->getEntityManager($this->getEventManager(null, $userCallback, get_class($user)));
        $em->persist($user);
        $em->persist($user2);
        $em->flush();

        $entity = new \BehaviorFixtures\ORM\BlameableEntity();

        $em->persist($entity);
        $em->flush();
        $id = $entity->getId();
        $createdBy = $entity->getCreatedBy();
        $this->listener->setUser($user2); // switch user for update

        $entity = $em->getRepository('BehaviorFixtures\ORM\BlameableEntity')->find($id);
        $entity->setTitle('test'); // need to modify at least one column to trigger onUpdate
        $em->flush();
        $em->clear();

        $this->assertInstanceOf('BehaviorFixtures\\ORM\\UserEntity', $entity->getCreatedBy(), 'createdBy is a user object');
        $this->assertEquals($createdBy->getUsername(), $entity->getCreatedBy()->getUsername(), 'createdBy is constant');
        $this->assertEquals($user2->getUsername(), $entity->getUpdatedBy()->getUsername());

        $this->assertNotEquals(
            $entity->getCreatedBy(),
            $entity->getUpdatedBy(),
            'createBy and updatedBy have diverged since new update'
        );
    }
}
