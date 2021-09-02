<?php

namespace KTPL\AkeneoTrashBundle\Repository\InternalApi;

use Akeneo\Pim\Enrichment\Component\Category\Model\CategoryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use Doctrine\ORM\EntityManager;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface;
use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface;

class CategoryRepository extends NestedTreeRepository implements
    DatagridRepositoryInterface,
    MassActionRepositoryInterface
{
    const CATEGORY_RESOURCE_NAME = 'category';

    /** @var UserContext */
    protected $userContext;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /**
     * @param UserContext        $userContext
     * @param EntityManager      $em
     * @param string             $class
     * @param AkeneoTrashManager $akeneoTrashManager
     */
    public function __construct(
        UserContext $userContext,
        EntityManager $em,
        $class,
        AkeneoTrashManager $akeneoTrashManager
    ) {
        parent::__construct($em, $em->getClassMetadata($class));

        $this->userContext = $userContext;
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     */
    public function createDatagridQueryBuilder()
    {
        $qb = $this->createQueryBuilder('c');
        $rootAlias = $qb->getRootAlias();

        $qb
            ->select($rootAlias)
            ->addSelect('translation.label')
            ->leftJoin($rootAlias . '.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->andWhere($rootAlias.'.code in (:categoriesCode)')
            ->setParameter('categoriesCode', $this->findCategoriesCodeToBeRender())
            ->setParameter('localeCode', $this->userContext->getCurrentLocaleCode());

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function applyMassActionParameters($qb, $inset, array $values)
    {
        if ($values) {
            $rootAlias = $qb->getRootAlias();
            $valueWhereCondition =
                $inset
                    ? $qb->expr()->in($rootAlias, $values)
                    : $qb->expr()->notIn($rootAlias, $values);
            $qb->andWhere($valueWhereCondition);
        }

        if (null !== $qb->getDQLPart('where')) {
            $whereParts = $qb->getDQLPart('where')->getParts();
            $qb->resetDQLPart('where');

            foreach ($whereParts as $part) {
                if (!is_string($part) || !strpos($part, 'entityIds')) {
                    $qb->andWhere($part);
                }
            }
        }

        $qb->setParameters(
            $qb->getParameters()->filter(
                function ($parameter) {
                    return $parameter->getName() !== 'entityIds';
                }
            )
        );

        // remove limit of the query
        $qb->setMaxResults(null);
    }
    
    /**
     * Find the categories code to be render in the datagrid
     *
     * @return array
     */
    protected function findCategoriesCodeToBeRender()
    {
        return $this->akeneoTrashManager->getTrashResourcesCode(
            [
                self::CATEGORY_RESOURCE_NAME
            ]
        );
    }
}
