<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Job;

use Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Enrichment\Component\Product\ProductAndProductModel\Query\CountVariantProductsInterface;
use Akeneo\Pim\Enrichment\Component\Product\ProductModel\Query\CountProductModelsAndChildrenProductModelsInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Cursor\CursorInterface;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Restore products and product models
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class RestoreProductsAndProductModelsTasklet implements TaskletInterface
{
    /** @var StepExecution  */
    protected $stepExecution = null;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /** @var ProductQueryBuilderFactoryInterface */
    protected $pqbFactory;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var ObjectFilterInterface */
    protected $filter;

    /** @var int */
    protected $batchSize = 100;

    /** @var  CountProductModelsAndChildrenProductModelsInterface */
    private $countProductModelsAndChildrenProductModels;
    
    /** @var  CountVariantProductsInterface */
    private $countVariantProducts;
    
    /** @var  JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        AkeneoTrashManager $akeneoTrashManager,
        EntityManagerClearerInterface $cacheClearer,
        ObjectFilterInterface $filter,
        int $batchSize,
        CountProductModelsAndChildrenProductModelsInterface $countProductModelsAndChildrenProductModels,
        CountVariantProductsInterface $countVariantProducts,
        JobRepositoryInterface $jobRepository
    ) {
        $this->pqbFactory = $pqbFactory;
        $this->akeneoTrashManager = $akeneoTrashManager;
        $this->cacheClearer = $cacheClearer;
        $this->batchSize = $batchSize;
        $this->filter = $filter;
        $this->countProductModelsAndChildrenProductModels = $countProductModelsAndChildrenProductModels;
        $this->countVariantProducts = $countVariantProducts;
        $this->jobRepository = $jobRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution): void
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): void
    {
        if (null === $this->stepExecution) {
            throw new \InvalidArgumentException(
                sprintf('In order to execute "%s" you need to set a step execution.', static::class)
            );
        }

        $this->stepExecution->addSummaryInfo('restored_products', 0);
        $this->stepExecution->addSummaryInfo('restored_product_models', 0);

        $productsAndRootProductModels = $this->findSimpleProductsAndRootProductModels();
        $this->restore($productsAndRootProductModels);

        $subProductModels = $this->findSubProductModels();
        $this->restore($subProductModels);

        $variantProducts = $this->findVariantProducts();
        $this->restore($variantProducts);
    }

    private function findVariantProducts(): CursorInterface
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $options = ['filters' => $filters];

        $productQueryBuilder = $this->pqbFactory->create($options);
        $productQueryBuilder->addFilter('entity_type', Operators::EQUALS, ProductInterface::class);
        $productQueryBuilder->addFilter('parent', Operators::IS_NOT_EMPTY, null);

        return $productQueryBuilder->execute();
    }

    private function findSubProductModels(): CursorInterface
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $options = ['filters' => $filters];

        $productQueryBuilder = $this->pqbFactory->create($options);
        $productQueryBuilder->addFilter('entity_type', Operators::EQUALS, ProductModelInterface::class);
        $productQueryBuilder->addFilter('parent', Operators::IS_NOT_EMPTY, null);

        return $productQueryBuilder->execute();
    }

    /**
     * @return CursorInterface
     */
    private function findSimpleProductsAndRootProductModels(): CursorInterface
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $options = ['filters' => $filters];

        $productQueryBuilder = $this->pqbFactory->create($options);
        $productQueryBuilder->addFilter('parent', Operators::IS_EMPTY, null);

        return $productQueryBuilder->execute();
    }

    /**
     * @param CursorInterface $products
     */
    private function restore(CursorInterface $products): void
    {
        $loopCount = 0;
        $entitiesToRemove = [];
        while ($products->valid()) {
            $product = $products->current();
            if (!$this->filter->filterObject($product, 'pim.enrich.product.restore')) {
                $entitiesToRemove[] = $product;
            } else {
                $this->stepExecution->incrementSummaryInfo('skip');
            }

            $loopCount++;
            if ($this->batchSizeIsReached($loopCount)) {
                $this->doRestore($entitiesToRemove);
                $entitiesToRemove = [];
            }
            $products->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRemove)) {
            $this->doRestore($entitiesToRemove);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Restore given products and product models, clears the cache and increments the summary info.
     *
     * @param array $entities
     */
    protected function doRestore(array $entities): void
    {
        $products = $this->filterProducts($entities);
        $productModels = $this->filterProductModels($entities);

        $restoredProductsCount = $this->countProductsToRestore($products, $productModels);
        $restoredProductModelsCount = $this->countProductModelsToRestore($productModels);

        $this->akeneoTrashManager->removeItemsFromTrash($products);
        $this->stepExecution->incrementSummaryInfo('restored_products', $restoredProductsCount);

        $this->akeneoTrashManager->removeItemsFromTrash($productModels);
        $this->stepExecution->incrementSummaryInfo('restored_product_models', $restoredProductModelsCount);

        $this->cacheClearer->clear();
    }

    /**
     * @param ProductInterface[] $products
     * @param ProductModelInterface[] $productModels
     *
     * @return int
     */
    private function countProductsToRestore(array $products, array $productModels): int
    {
        return count($products) + $this->countVariantProducts->forProductModelCodes(
            array_map(
                function (ProductModelInterface $productModel) {
                    return $productModel->getCode();
                },
                $productModels
            )
        );
    }

    /**
     * @param ProductModelInterface[] $productModels
     *
     * @return int
     */
    private function countProductModelsToRestore(array $productModels): int
    {
        return $this->countProductModelsAndChildrenProductModels->forProductModelCodes(
            array_map(
                function (ProductModelInterface $productModel) {
                    return $productModel->getCode();
                },
                $productModels
            )
        );
    }

    /**
     * @param int $loopCount
     *
     * @return bool
     */
    private function batchSizeIsReached(int $loopCount): bool
    {
        return 0 === $loopCount % $this->batchSize;
    }

    /**
     * Returns only entities that are products in the given array.
     *
     * @param array $entities
     *
     * @return ProductInterface[]
     */
    private function filterProducts(array $entities): array
    {
        return array_values(
            array_filter($entities, function ($item) {
                return $item instanceof ProductInterface;
            })
        );
    }

    /**
     * Returns only entities that are product models in the given array.
     *
     * @param array $entities
     *
     * @return ProductModelInterface[]
     */
    private function filterProductModels(array $entities): array
    {
        return array_values(
            array_filter($entities, function ($item) {
                return $item instanceof ProductModelInterface;
            })
        );
    }
}
