<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ResourceBundle\Doctrine\ORM;

use Doctrine\ORM\EntityRepository as BaseEntityRepository;
use Sylius\Bundle\ResourceBundle\Model\RepositoryInterface;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Doctrine\DBAL\LockMode;

/**
 * Doctrine ORM driver entity repository.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 */
class EntityRepository extends BaseEntityRepository implements RepositoryInterface
{
    public function createNew()
    {
        $className = $this->getClassName();

        return new $className;
    }

    /**
     * @param mixed $id
     * @param int $lockMode
     * @param null $lockVersion
     *
     * @return null
     */
    public function find($id, $lockMode = LockMode::NONE, $lockVersion = null)
    {
        $result = null;
        try {
            $result = $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
        } catch (ORMInvalidArgumentException $exception) {
            //do nothing
        } catch (ORMException $exception) {
            //do nothing
        }

        return $result;
    }

    public function findAll()
    {
        return $this
            ->getCollectionQueryBuilder()
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param mixed[] $criteria
     * @param mixed[] $orderBy
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function findOneBy(array $criteria, array $orderBy = [])
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);
        return $persister->load($criteria, null, null, [], null, 1, $orderBy);
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $queryBuilder = $this->getCollectionQueryBuilder();

        $this->applyCriteria($queryBuilder, $criteria);
        $this->applySorting($queryBuilder, $orderBy);

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        if (null !== $offset) {
            $queryBuilder->setFirstResult($offset);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult()
        ;
    }

    public function createPaginator(array $criteria = null, array $orderBy = null)
    {
        $queryBuilder = $this->getCollectionQueryBuilder();

        $this->applyCriteria($queryBuilder, $criteria);
        $this->applySorting($queryBuilder, $orderBy);

        return $this->getPaginator($queryBuilder);
    }

    public function getPaginator(QueryBuilder $queryBuilder)
    {
        return new Pagerfanta(new DoctrineORMAdapter($queryBuilder));
    }

    protected function getQueryBuilder()
    {
        return $this->createQueryBuilder($this->getAlias());
    }

    protected function getCollectionQueryBuilder()
    {
        return $this->createQueryBuilder($this->getAlias());
    }

    protected function applyCriteria(QueryBuilder $queryBuilder, array $criteria = null)
    {
        if (null === $criteria) {
            return;
        }

        $alias = $this->getAlias();

        foreach ($criteria as $property => $value) {
            $queryBuilder
                ->andWhere($alias.'.'.$property.' = :'.$property)
                ->setParameter($property, $value)
            ;
        }
    }

    protected function applySorting(QueryBuilder $queryBuilder, array $sorting = null)
    {
        if (null === $sorting) {
            return;
        }

        $alias = $this->getAlias();

        foreach ($sorting as $property => $order) {
            $queryBuilder->orderBy($alias.'.'.$property, $order);
        }
    }

    protected function getAlias()
    {
        return 'o';
    }
}
