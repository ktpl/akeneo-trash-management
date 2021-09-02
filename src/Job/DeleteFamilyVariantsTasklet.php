<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Job;

use Akeneo\Pim\Structure\Component\Repository\FamilyVariantRepositoryInterface;
use Akeneo\Tool\Component\Batch\Item\TrackableTaskletInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\BulkRemoverInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Delete family variants
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class DeleteFamilyVariantsTasklet implements TaskletInterface, TrackableTaskletInterface
{
    /** @var StepExecution */
    protected $stepExecution = null;

    /** @var FamilyVariantRepositoryInterface */
    protected $familyVariantRepository;

    /** @var BulkRemoverInterface */
    protected $familyRemover;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var JobStopper */
    private $jobStopper;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        FamilyVariantRepositoryInterface $familyVariantRepository,
        BulkRemoverInterface $familyRemover,
        EntityManagerClearerInterface $cacheClearer,
        int $batchSize,
        JobStopper $jobStopper,
        JobRepositoryInterface $jobRepository
    ) {
        $this->familyVariantRepository = $familyVariantRepository;
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

        $familyVariants = $this->findFamilies();

        $this->stepExecution->setTotalItems($familyVariants->count());
        $this->stepExecution->addSummaryInfo('deleted_family_variants', 0);

        $this->delete($familyVariants);
    }

    /**
     * Find the family variants
     *
     * @return ArrayCollection
     */
    private function findFamilies()
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        
        $familyVariants = array_filter(array_map(function ($id) {
            return $this->familyVariantRepository->find($id);
        }, $filters[0]['value']));

        return new ArrayCollection($familyVariants);
    }

    /**
     * Perform family variants deletion
     *
     * @param ArrayCollection $familyVariants
     */
    private function delete(ArrayCollection $familyVariants): void
    {
        $loopCount = 0;
        $entitiesToRemove = [];
        while ($familyVariant = $familyVariants->current()) {
            $entitiesToRemove[] = $familyVariant;
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
            $familyVariants->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRemove)) {
            $this->doDelete($entitiesToRemove);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Deletes given family variants, clears the cache and increments the summary info.
     *
     * @param array $familyVariants
     */
    protected function doDelete(array $familyVariants): void
    {
        $deletedFamilyVariantsCount = count($familyVariants);

        $this->familyRemover->removeAll($familyVariants);
        $this->stepExecution->incrementSummaryInfo('deleted_family_variants', $deletedFamilyVariantsCount);
        $this->stepExecution->incrementProcessedItems($deletedFamilyVariantsCount);

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
