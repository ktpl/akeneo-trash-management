<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Job;

use Akeneo\Tool\Component\Batch\Item\TrackableTaskletInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\BulkRemoverInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Delete categories
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class DeleteCategoriesTasklet implements TaskletInterface, TrackableTaskletInterface
{
    /** @var StepExecution */
    protected $stepExecution = null;

    /** @var CategoryRepositoryInterface */
    protected $categoryRepository;

    /** @var BulkRemoverInterface */
    protected $categoryRemover;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var JobStopper */
    private $jobStopper;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        BulkRemoverInterface $categoryRemover,
        EntityManagerClearerInterface $cacheClearer,
        int $batchSize,
        JobStopper $jobStopper,
        JobRepositoryInterface $jobRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryRemover = $categoryRemover;
        $this->cacheClearer = $cacheClearer;
        $this->batchSize = $batchSize;
        $this->jobStopper = $jobStopper;
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

        $categories = $this->findCategories();
        $this->stepExecution->setTotalItems($categories->count());
        $this->stepExecution->addSummaryInfo('deleted_categories', 0);

        $this->delete($categories);
    }

    /**
     * Find the categories
     *
     * @return ArrayCollection
     */
    private function findCategories()
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');

        return $this->categoryRepository->getCategoriesByIds($filters[0]['value']);
    }

    /**
     * Perform categories deletion
     *
     * @param ArrayCollection $categories
     */
    private function delete(ArrayCollection $categories): void
    {
        $loopCount = 0;
        $entitiesToRemove = [];
        while ($category = $categories->current()) {
            $entitiesToRemove[] = $category;
            $loopCount++;

            if ($this->batchSizeIsReached($loopCount)) {
                if ($this->jobStopper->isStopping($this->stepExecution)) {
                    $this->jobStopper->stop($this->stepExecution);
                    return;
                }
                $this->doDelete($entitiesToRemove);
                $this->jobRepository->updateStepExecution($this->stepExecution);
                $entitiesToRemove = [];
            }
            $categories->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRemove)) {
            $this->doDelete($entitiesToRemove);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Deletes given categories, clears the cache and increments the summary info.
     *
     * @param array $categories
     */
    protected function doDelete(array $categories): void
    {
        $deletedCategoryCount = count($categories);

        $this->categoryRemover->removeAll($categories);
        $this->stepExecution->incrementSummaryInfo('deleted_categories', $deletedCategoryCount);
        $this->stepExecution->incrementProcessedItems($deletedCategoryCount);

        $this->cacheClearer->clear();
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

    public function isTrackable(): bool
    {
        return true;
    }
}
