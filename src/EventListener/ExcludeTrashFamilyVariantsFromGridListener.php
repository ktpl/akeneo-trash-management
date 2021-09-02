<?php
declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\EventListener;

use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Oro\Bundle\PimDataGridBundle\Datasource\FamilyVariantDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;

/**
 * Remove trash family variants from family variant grid
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ExcludeTrashFamilyVariantsFromGridListener
{
    const FAMILY_VARIANT_RESOURCE_NAME = 'family_variant';

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    public function __construct(AkeneoTrashManager $akeneoTrashManager)
    {
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        $dataSource = $event->getDatagrid()->getDatasource();

        if ($dataSource instanceof FamilyVariantDatasource) {
            $resourcesCodeTobeRenderInGridGrid = $this->akeneoTrashManager->getTrashResourcesCode(
                [
                    self::FAMILY_VARIANT_RESOURCE_NAME
                ]
            );

            if (!empty($resourcesCodeTobeRenderInGridGrid)) {
                $qb = $dataSource->getQueryBuilder();
                $rootAlias = $qb->getRootAlias();
                $qb->andWhere($rootAlias.'.code not in (:codes)')
                    ->setParameter('codes', $resourcesCodeTobeRenderInGridGrid);
            }
        }
    }
}
