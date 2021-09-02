<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Job;

use Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface;
use Akeneo\Tool\Component\Batch\Item\TrackableTaskletInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\BulkRemoverInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Delete families
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class DeleteFamiliesTasklet implements TaskletInterface, TrackableTaskletInterface
{
    /** @var StepExecution */
    protected $stepExecution = null;

    /** @var FamilyRepositoryInterface */
    protected $familyRepository;

    /** @var BulkRemoverInterface */
    protected $familyRemover;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var JobStopper */
    private $jobStopper;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        FamilyRepositoryInterface $familyRepository,
        BulkRemoverInterface $familyRemover,
        EntityManagerClearerInterface $cacheClearer,
        int $batchSize,
        JobStopper $jobStopper,
        JobRepositoryInterface $jobRepository
    ) {
        $this->familyRepository = $familyRepository;
        $this->familyRemover = $familyRemover;
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

        $families = $this->findFamilies();

        $this->stepExecution->setTotalItems($families->count());
        $this->stepExecution->addSummaryInfo('deleted_families', 0);

        $this->delete($families);
    }

    /**
     * Find the families
     *
     * @return ArrayCollection
     */
    private function findFamilies()
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        
        return new ArrayCollection($this->familyRepository->findByIds($filters[0]['value']));
    }

    /**
     * Perform families deletion
     *
     * @param ArrayCollection $families
     */
    private function delete(ArrayCollection $families): void
    {
        $loopCount = 0;
        $entitiesToRemove = [];
        while ($family = $families->current()) {
            $entitiesToRemove[] = $family;
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
            $families->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRemove)) {
            $this->doDelete($entitiesToRemove);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Deletes given families, clears the cache and increments the summary info.
     *
     * @param array $families
     */
    protected function doDelete(array $families): void
    {
        $deletedFamiliesCount = count($families);

        $this->familyRemover->removeAll($families);
        $this->stepExecution->incrementSummaryInfo('deleted_families', $deletedFamiliesCount);
        $this->stepExecution->incrementProcessedItems($deletedFamiliesCount);

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
