<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Doctrine\ORM\Repository\InternalApi;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface;

/**
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FamilyVariantRepository implements DatagridRepositoryInterface
{
    const FAMILY_VARIANT_RESOURCE_NAME = 'family_variant';

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var string */
    private $entityName;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $entityName
     * @param AkeneoTrashManager $akeneoTrashManager
     */
    public function __construct(EntityManagerInterface $entityManager, $entityName, AkeneoTrashManager $akeneoTrashManager)
    {
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * @param array $parameters
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createDatagridQueryBuilder($parameters = []): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()->select('fv')->from($this->entityName, 'fv');
        $rootAlias = $qb->getRootAlias();

        $labelExpr = sprintf(
            '(CASE WHEN translation.label IS NULL THEN %s.code ELSE translation.label END)',
            $rootAlias
        );

        $qb
            ->select($rootAlias)
            ->leftJoin($rootAlias . '.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->andWhere($rootAlias.'.code in (:codes)')
            ->setParameter('codes', $this->findFamilyVariantsCodeToBeRender());

        return $qb;
    }

    /**
     * Find the family variants code to be render in the datagrid
     *
     * @return array
     */
    protected function findFamilyVariantsCodeToBeRender()
    {
        return $this->akeneoTrashManager->getTrashResourcesCode(
            [
                self::FAMILY_VARIANT_RESOURCE_NAME
            ]
        );
    }
}
