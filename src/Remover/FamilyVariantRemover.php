<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Remover;

use Akeneo\Pim\Enrichment\Component\Product\ProductAndProductModel\Query\CountEntityWithFamilyVariantInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Akeneo\Pim\Structure\Component\Remover\FamilyVariantRemover as BaseFamilyVariantRemover;
use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Removes a family variant if there is no entity with family variants already associated with it.
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FamilyVariantRemover extends BaseFamilyVariantRemover
{
    /** @var ObjectManager */
    private $objectManager;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var CountEntityWithFamilyVariantInterface */
    private $counter;

    /** @var AkeneoTrashManager */
    private $akeneoTrashManager;

    /**
     * @param ObjectManager                         $objectManager
     * @param EventDispatcherInterface              $eventDispatcher
     * @param CountEntityWithFamilyVariantInterface $counter
     * @param AkeneoTrashManager                    $akeneoTrashManager
     */
    public function __construct(
        ObjectManager $objectManager,
        EventDispatcherInterface $eventDispatcher,
        CountEntityWithFamilyVariantInterface $counter,
        AkeneoTrashManager $akeneoTrashManager
    ) {
        $this->objectManager = $objectManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->counter = $counter;
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($familyVariant, array $options = []): RemoverInterface
    {
        if (!$familyVariant instanceof FamilyVariantInterface) {
            throw InvalidObjectException::objectExpected(
                ClassUtils::getClass($familyVariant),
                FamilyVariantInterface::class
            );
        }

        if ($this->hasEntityWithFamilyVariant($familyVariant)) {
            throw new \LogicException(
                sprintf(
                    'Family variant "%s", could not be removed as it is used by some entities with family variants.',
                    $familyVariant->getCode()
                )
            );
        }

        $options['unitary'] = true;

        $this->akeneoTrashManager->moveItemIntoTrash($familyVariant, $options);
        
        return $this;
    }

    private function hasEntityWithFamilyVariant(FamilyVariantInterface $familyVariant): bool
    {
        return 0 !== $this->counter->belongingToFamilyVariant($familyVariant);
    }
}
