<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Remover;

use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Akeneo\Pim\Structure\Component\Remover\FamilyRemover as BaseFamilyRemover;
use Akeneo\Pim\Enrichment\Component\Product\ProductAndProductModel\Query\CountProductsWithFamilyInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Removes a family if no product uses it and no family variant belong to it.
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FamilyRemover extends BaseFamilyRemover
{
    /** @var ObjectManager */
    private $objectManager;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var CountProductsWithFamilyInterface */
    private $counter;

    /** @var AkeneoTrashManager */
    private $akeneoTrashManager;
    
    /**
     * @param ObjectManager                    $objectManager
     * @param EventDispatcherInterface         $eventDispatcher
     * @param CountProductsWithFamilyInterface $counter
     * @param AkeneoTrashManager               $akeneoTrashManager
     */
    public function __construct(
        ObjectManager $objectManager,
        EventDispatcherInterface $eventDispatcher,
        CountProductsWithFamilyInterface $counter,
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
    public function remove($family, array $options = []): void
    {
        $this->ensureIsFamily($family);
        $this->ensureFamilyHasNoVariants($family);
        $this->ensureFamilyHasNoProducts($family);

        $options['unitary'] = true;

        $this->akeneoTrashManager->moveItemIntoTrash($family, $options);
    }

    private function ensureIsFamily($family): void
    {
        if (! $family instanceof FamilyInterface) {
            throw InvalidObjectException::objectExpected(
                ClassUtils::getClass($family),
                FamilyInterface::class
            );
        }
    }

    /**
     * @param FamilyInterface $family
     *
     * @return void
     */
    private function ensureFamilyHasNoVariants(FamilyInterface $family): void
    {
        if (! $family->getFamilyVariants()->isEmpty()) {
            throw new \LogicException(sprintf(
                'Can not remove family "%s" because it is linked to family variants.',
                $family->getCode()
            ));
        }
    }

    /**
     * @param FamilyInterface $family
     *
     * @return void
     */
    private function ensureFamilyHasNoProducts(FamilyInterface $family): void
    {
        if ($this->counter->count($family) > 0) {
            throw new \LogicException(sprintf(
                'Family "%s" could not be removed as it still has products',
                $family->getCode()
            ));
        }
    }
}
