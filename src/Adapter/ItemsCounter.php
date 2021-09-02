<?php

namespace KTPL\AkeneoTrashBundle\Adapter;

use Akeneo\Pim\Enrichment\Bundle\Storage\Sql\ProductGrid\CountImpactedProducts;
use KTPL\AkeneoTrashBundle\Adapter\OroToPimGridFilterAdapter;

/**
 * Counts the number of items selected in the grid.
 */
class ItemsCounter
{
    /** @var CountImpactedProducts */
    private $countImpactedProducts;

    public function __construct(CountImpactedProducts $countImpactedProducts)
    {
        $this->countImpactedProducts = $countImpactedProducts;
    }

    /**
     * @param string $gridName
     * @param array  $filters
     *
     * @return int
     * @throws \Exception
     */
    public function count(string $gridName, array $filters): int
    {
        if ($gridName === OroToPimGridFilterAdapter::PRODUCT_TRASH_GRID_NAME) {
            return $this->countImpactedProducts->count($filters);
        }

        if (!isset($filters[0]['value'])) {
            throw new \Exception('There should one filter containing the items to filter.');
        }

        return count($filters[0]['value']);
    }
}
