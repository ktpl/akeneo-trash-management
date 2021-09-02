<?php

namespace KTPL\AkeneoTrashBundle\Doctrine\ORM\Repository\InternalApi;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\InternalApi\FamilySearchableRepository as BaseFamilySearchableRepository;
use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Family searchable repository
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FamilySearchableRepository extends BaseFamilySearchableRepository
{
    const FAMILY_RESOURCE_NAME = 'family';

    /** @var AkeneoTrashManager*/
    protected $akeneoTrashManager;

    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $entityName
     * @param AkeneoTrashManager     $akeneoTrashManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        $entityName,
        AkeneoTrashManager $akeneoTrashManager
    ) {
        parent::__construct($entityManager, $entityName);

        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     *
     * @return FamilyInterface[]
     */
    public function findBySearch($search = null, array $options = [])
    {
        $families = parent::findBySearch($search, $options);
        foreach ($families as $familyKey => $family) {
            if (in_array($family->getCode(), $this->getAllTrashFamiliesCodes())) {
                unset($families[$familyKey]);
            }
        }
        
        return $families;
    }

    /**
     * Return families codes of the trash families
     *
     * @return array
     */
    protected function getAllTrashFamiliesCodes()
    {
        return $this->akeneoTrashManager->getTrashResourcesCode(
            [
                self::FAMILY_RESOURCE_NAME
            ]
        );
    }
}
