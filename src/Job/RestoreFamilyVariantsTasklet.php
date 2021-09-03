<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Job;

use Akeneo\Pim\Structure\Component\Repository\FamilyVariantRepositoryInterface;
use Akeneo\Tool\Component\Batch\Item\TrackableTaskletInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Connector\Step\TaskletInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Restore family variants
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class RestoreFamilyVariantsTasklet implements TaskletInterface
{
    /** @var StepExecution */
    protected $stepExecution = null;

    /** @var FamilyVariantRepositoryInterface */
    protected $familyVariantRepository;

    /** @var AkeneoTrashManager */
    protected $akeneoTrashManager;

    /** @var EntityManagerClearerInterface */
    protected $cacheClearer;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    public function __construct(
        FamilyVariantRepositoryInterface $familyVariantRepository,
        AkeneoTrashManager $akeneoTrashManager,
        EntityManagerClearerInterface $cacheClearer,
        int $batchSize,
        JobRepositoryInterface $jobRepository
    ) {
        $this->familyVariantRepository = $familyVariantRepository;
        $this->akeneoTrashManager = $akeneoTrashManager;
        $this->cacheClearer = $cacheClearer;
        $this->batchSize = $batchSize;
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

        $familyVariants = $this->findFamilyVariants();

        $this->stepExecution->addSummaryInfo('restored_family_variants', 0);

        $this->restore($familyVariants);
    }

    /**
     * Find the family variants
     *
     * @return ArrayCollection
     */
    private function findFamilyVariants()
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');

        $familyVariants = array_filter(array_map(function ($id) {
            return $this->familyVariantRepository->find($id);
        }, $filters[0]['value']));

        return new ArrayCollection($familyVariants);
    }

    /**
     * Perform family variants restore
     *
     * @param ArrayCollection $familyVariants
     */
    private function restore(ArrayCollection $familyVariants): void
    {
        $loopCount = 0;
        $entitiesToRestore = [];
        while ($familyVariant = $familyVariants->current()) {
            $entitiesToRestore[] = $familyVariant;
            $loopCount++;

            if ($this->batchSizeIsReached($loopCount)) {
                $this->doRestore($entitiesToRestore);
                $entitiesToRestore = [];
            }
            $familyVariants->next();
            $this->stepExecution->incrementReadCount();
        }

        if (!empty($entitiesToRestore)) {
            $this->doRestore($entitiesToRestore);
            $this->jobRepository->updateStepExecution($this->stepExecution);
        }
    }

    /**
     * Restore given family variants, clears the cache and increments the summary info.
     *
     * @param array $familyVariants
     */
    protected function doRestore(array $familyVariants): void
    {
        $restoredFamilyVariantCount = count($familyVariants);

        $this->akeneoTrashManager->removeItemsFromTrash($familyVariants);
        $this->stepExecution->incrementSummaryInfo('restored_family_variants', $restoredFamilyVariantCount);

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
}
