<?php

namespace KTPL\AkeneoTrashBundle\Doctrine\ORM\Repository;

use Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Repository\ProductCategoryRepository as BaseProductCategoryRepository;
use Doctrine\ORM\EntityManager;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Product category repository
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ProductCategoryRepository extends BaseProductCategoryRepository
{
    const CATEGORY_RESOURCE_NAME = 'category';

    /** @var AkeneoTrashManager*/
    protected $akeneoTrashManager;

    /**
     * @param EntityManager      $em
     * @param string             $entityName
     * @param string             $categoryClass
     * @param AkeneoTrashManager $akeneoTrashManager
     */
    public function __construct(EntityManager $em, $entityName, $categoryClass, AkeneoTrashManager $akeneoTrashManager)
    {
        parent::__construct($em, $entityName, $categoryClass);

        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemCountByTree($item)
    {
        $itemCountTree = parent::getItemCountByTree($item);
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();
        
        foreach ($itemCountTree as $itemCountTreeKey => $itemTree) {
            if (in_array($itemTree['tree']->getCode(), $categoriesCodesToExclude)) {
                unset($itemCountTree[$itemCountTreeKey]);
            }
        }

        return $itemCountTree;
    }

    /**
     * Return categories codes of the trash categories
     *
     * @return array
     */
    protected function getAllTrashCategoriesCodes()
    {
        return $this->akeneoTrashManager->getTrashResourcesCode(
            [
                self::CATEGORY_RESOURCE_NAME
            ]
        );
    }
}
