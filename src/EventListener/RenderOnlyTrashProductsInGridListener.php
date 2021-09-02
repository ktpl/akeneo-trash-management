<?php
declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\EventListener;

use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Oro\Bundle\PimDataGridBundle\Datasource\ProductAndProductModelDatasource;
use Oro\Bundle\PimDataGridBundle\Datasource\ProductDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;

/**
 * Render only trashed product in the grid
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class RenderOnlyTrashProductsInGridListener
{
    const PRODUCT_RESOURCE_NAME = 'product';

    const PRODUCT_MODEL_RESOURCE_NAME = 'product_model';

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    public function __construct(AkeneoTrashManager $akeneoTrashManager)
    {
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    public function onBuildAfter(BuildAfter $event): void
    {
        $dataSource = $event->getDatagrid()->getDatasource();
        if ($dataSource instanceof ProductDatasource || $dataSource instanceof ProductAndProductModelDatasource) {
            $resourcesCodeTobeRenderInGridGrid = $this->akeneoTrashManager->getTrashResourcesCode(
                [
                    self::PRODUCT_RESOURCE_NAME,
                    self::PRODUCT_MODEL_RESOURCE_NAME
                ]
            );

            $qb = $dataSource->getQueryBuilder();
            $clause = [
                'terms' => [
                    'identifier' => $resourcesCodeTobeRenderInGridGrid,
                ],
            ];

            $qb->addFilter($clause);
        }
    }
}
