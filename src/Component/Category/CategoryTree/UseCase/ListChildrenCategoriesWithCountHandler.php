<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Component\Category\CategoryTree\UseCase;

use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\Query;
use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\ReadModel;
use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\UseCase\ListChildrenCategoriesWithCount;
use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\UseCase\ListChildrenCategoriesWithCountHandler as BaseListChildrenCategoriesWithCountHandler;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ListChildrenCategoriesWithCountHandler extends BaseListChildrenCategoriesWithCountHandler
{
    const CATEGORY_RESOURCE_NAME = 'category';

    /** @var AkeneoTrashManager*/
    protected $akeneoTrashManager;

    /**
     * @param CategoryRepositoryInterface                                    $categoryRepository
     * @param UserContext                                                    $userContext
     * @param Query\ListChildrenCategoriesWithCountIncludingSubCategories    $listAndCountIncludingSubCategories
     * @param Query\ListChildrenCategoriesWithCountNotIncludingSubCategories $listAndCountNotIncludingSubCategories
     * @param AkeneoTrashManager                                             $akeneoTrashManager
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        UserContext $userContext,
        Query\ListChildrenCategoriesWithCountIncludingSubCategories $listAndCountIncludingSubCategories,
        Query\ListChildrenCategoriesWithCountNotIncludingSubCategories $listAndCountNotIncludingSubCategories,
        AkeneoTrashManager $akeneoTrashManager
    ) {
        parent::__construct(
            $categoryRepository,
            $userContext,
            $listAndCountIncludingSubCategories,
            $listAndCountNotIncludingSubCategories
        );

        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * @param ListChildrenCategoriesWithCount $query
     *
     * @return ReadModel\ChildCategory[]
     */
    public function handle(ListChildrenCategoriesWithCount $query): array
    {
        $categories = parent::handle($query);
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();

        foreach ($categories as $categoryKey => $category) {
            if (in_array($category->code(), $categoriesCodesToExclude)) {
                unset($categories[$categoryKey]);
            }
        }

        return $categories;
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
