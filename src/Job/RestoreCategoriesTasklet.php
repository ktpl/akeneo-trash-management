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
use Doctrine\Common\Collections\ArrayCollection;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Restore categories
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class RestoreCategoriesTasklet implements TaskletInterface, TrackableTaskletInterface
{
    /** @var StepExecution */
    protected $stepExecution = null;

    /** @var CategoryRepositoryInterface */
    protected $categoryRepository;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var JobStopper */
    private $jobStopper;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        AkeneoTrashManager $akeneoTrashManager,
        EntityManagerClearerInterface $cacheClearer,
        int $batchSize,
        JobStopper $jobStopper,
        JobRepositoryInterface $jobRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->akeneoTrashManager = $akeneoTrashManager;
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
        $this->stepExecution->addSummaryInfo('restored_categories', 0);

        $this->restore($categories);
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
     * Perform categories restore
     *
     * @param ArrayCollection $categories
     */
    private function restore(ArrayCollection $categories): void
    {
        $loopCount = 0;
        $entitiesToRestore = [];
        while ($category = $categories->current()) {
            $entitiesToRestore[] = $category;
            $loopCount++;

            if ($this->batchSizeIsReached($loopCount)) {
                if ($this->jobStopper->isStopping($this->stepExecution)) {
                    $this->jobStopper->stop($this->stepExecution);
                    return;
                }
                $this->doRestore($entitiesToRestore);
                $this->jobRepository->updateStepExecution($this->stepExecution);
                $entitiesToRestore = [];
            }
            $categories->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRestore)) {
            $this->doRestore($entitiesToRestore);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Restore given categories, clears the cache and increments the summary info.
     *
     * @param array $categories
     */
    protected function doRestore(array $categories): void
    {
        $restoredCategoriesCount = count($categories);

        $this->akeneoTrashManager->removeItemsFromTrash($categories);
        $this->stepExecution->incrementSummaryInfo('restored_categories', $restoredCategoriesCount);
        $this->stepExecution->incrementProcessedItems($restoredCategoriesCount);

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
