<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Component\Category\CategoryTree\UseCase;

use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\Query;
use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\ReadModel;
use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\UseCase\ListRootCategoriesWithCount;
use Akeneo\Pim\Enrichment\Component\Category\CategoryTree\UseCase\ListRootCategoriesWithCountHandler as BaseListRootCategoriesWithCountHandler;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ListRootCategoriesWithCountHandler extends BaseListRootCategoriesWithCountHandler
{
    const CATEGORY_RESOURCE_NAME = 'category';

    /** @var AkeneoTrashManager*/
    protected $akeneoTrashManager;

    /**
     * @param CategoryRepositoryInterface                                $categoryRepository
     * @param UserContext                                                $userContext
     * @param Query\ListRootCategoriesWithCountIncludingSubCategories    $listAndCountIncludingSubCategories
     * @param Query\ListRootCategoriesWithCountNotIncludingSubCategories $listAndCountNotIncludingSubCategories
     * @param AkeneoTrashManager                                         $akeneoTrashManager
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        UserContext $userContext,
        Query\ListRootCategoriesWithCountIncludingSubCategories $listAndCountIncludingSubCategories,
        Query\ListRootCategoriesWithCountNotIncludingSubCategories $listAndCountNotIncludingSubCategories,
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
     * @param ListRootCategoriesWithCount $query
     *
     * @return ReadModel\RootCategory[]
     */
    public function handle(ListRootCategoriesWithCount $query): array
    {
        $rootCategories = parent::handle($query);
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();

        foreach ($rootCategories as $rootCategoryKey => $rootCategory) {
            if (in_array($rootCategory->code(), $categoriesCodesToExclude)) {
                unset($rootCategories[$rootCategoryKey]);
            }
        }

        return $rootCategories;
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
