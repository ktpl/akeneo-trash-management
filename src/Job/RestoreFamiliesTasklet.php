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
use Doctrine\Common\Collections\ArrayCollection;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Restore families
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class RestoreFamiliesTasklet implements TaskletInterface, TrackableTaskletInterface
{
    /** @var StepExecution */
    protected $stepExecution = null;

    /** @var FamilyRepositoryInterface */
    protected $familyRepository;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var JobStopper */
    private $jobStopper;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        FamilyRepositoryInterface $familyRepository,
        AkeneoTrashManager $akeneoTrashManager,
        EntityManagerClearerInterface $cacheClearer,
        int $batchSize,
        JobStopper $jobStopper,
        JobRepositoryInterface $jobRepository
    ) {
        $this->familyRepository = $familyRepository;
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

        $families = $this->findFamilies();

        $this->stepExecution->setTotalItems($families->count());
        $this->stepExecution->addSummaryInfo('restored_families', 0);

        $this->restore($families);
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
     * Perform families restore
     *
     * @param ArrayCollection $families
     */
    private function restore(ArrayCollection $families): void
    {
        $loopCount = 0;
        $entitiesToRestore = [];
        while ($family = $families->current()) {
            $entitiesToRestore[] = $family;
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
            $families->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRestore)) {
            $this->doRestore($entitiesToRestore);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Restore given families, clears the cache and increments the summary info.
     *
     * @param array $families
     */
    protected function doRestore(array $families): void
    {
        $restoredFamiliesCount = count($families);

        $this->akeneoTrashManager->removeItemsFromTrash($families);
        $this->stepExecution->incrementSummaryInfo('restored_families', $restoredFamiliesCount);
        $this->stepExecution->incrementProcessedItems($restoredFamiliesCount);

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
