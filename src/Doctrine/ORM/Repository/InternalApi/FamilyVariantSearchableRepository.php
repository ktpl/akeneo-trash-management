<?php

namespace KTPL\AkeneoTrashBundle\Doctrine\ORM\Repository\InternalApi;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\InternalApi\FamilyVariantSearchableRepository as BaseFamilyVariantSearchableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FamilyVariantSearchableRepository extends BaseFamilyVariantSearchableRepository
{
    const FAMILY_VARIANT_RESOURCE_NAME = 'family_variant';

    /** @var AkeneoTrashManager*/
    protected $akeneoTrashManager;

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $entityName
     */
    public function __construct(EntityManagerInterface $entityManager, $entityName, AkeneoTrashManager $akeneoTrashManager)
    {
        parent::__construct($entityManager, $entityName);

        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     */
    public function findBySearch($search = null, array $options = [])
    {
        $familyVariants = parent::findBySearch($search, $options);
        foreach ($familyVariants as $familyVariantKey => $familyVariant) {
            if (in_array($familyVariant->getCode(), $this->getAllTrashFamilyVariantsCodes())) {
                unset($familyVariants[$familyVariantKey]);
            }
        }
        
        return $familyVariants;
    }

    /**
     * Return family variants codes of the trash family variants
     *
     * @return array
     */
    protected function getAllTrashFamilyVariantsCodes()
    {
        return $this->akeneoTrashManager->getTrashResourcesCode(
            [
                self::FAMILY_VARIANT_RESOURCE_NAME
            ]
        );
    }
}
