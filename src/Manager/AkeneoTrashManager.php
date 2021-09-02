<?php

namespace KTPL\AkeneoTrashBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use KTPL\AkeneoTrashBundle\Entity\AkeneoTrash;
use KTPL\AkeneoTrashBundle\Factory\AkeneoTrashFactory;
use KTPL\AkeneoTrashBundle\Repository\AkeneoTrashRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Akeneo trash manager
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class AkeneoTrashManager
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var AkeneoTrashRepository */
    protected $akeneoTrashRepository;

    /** @var AkeneoTrashFactory */
    protected $akeneoTrashFactory;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /** @var array */
    protected $resources = [
        'product' => [
            'resource_name' => 'Akeneo\Pim\Enrichment\Component\Product\Model\Product',
            'table' => 'pim_catalog_product',
            'code' => 'identifier'
        ],
        'product_model' => [
            'resource_name' => 'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModel',
            'table' => 'pim_catalog_product_model',
            'code' => 'code'
        ],
        'category' => [
            'resource_name' => 'Akeneo\Pim\Enrichment\Component\Category\Model\Category',
            'table' => 'pim_catalog_category',
            'code' => 'code'
        ],
        'family' => [
            'resource_name' => 'Akeneo\Pim\Structure\Component\Model\Family',
            'table' => 'pim_catalog_family',
            'code' => 'code'
        ],
        'family_variant' => [
            'resource_name' => 'Akeneo\Pim\Structure\Component\Model\FamilyVariant',
            'table' => 'pim_catalog_family_variant',
            'code' => 'code'
        ],
    ];

    /**
     * @param ObjectManager         $objectManager
     * @param AkeneoTrashRepository $akeneoTrashRepository
     * @param AkeneoTrashFactory    $akeneoTrashFactory
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(
        ObjectManager $objectManager,
        AkeneoTrashRepository $akeneoTrashRepository,
        AkeneoTrashFactory $akeneoTrashFactory,
        TokenStorageInterface $tokenStorage
    ) {
        $this->objectManager = $objectManager;
        $this->akeneoTrashRepository = $akeneoTrashRepository;
        $this->akeneoTrashFactory = $akeneoTrashFactory;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Move item into akeneo trash
     *
     * @param object $item
     * @param array  $options
     *
     * @return AkeneoTrash
     */
    public function moveItemIntoTrash($item, $options = [])
    {
        $userName = $this->tokenStorage->getToken()->getUsername();
        $resourceId = $item->getId();
        $resourceName = ClassUtils::getClass($item);
        $itemTrash = $this->akeneoTrashRepository->findOneBy([
            'resourceId' => $resourceId,
            'resourceName' => $resourceName
            ]);
        if (!$itemTrash) {
            $itemTrash = $this->akeneoTrashFactory->create();
            $itemTrash->setAuthor($userName);
            $itemTrash->setResourceId($resourceId);
            $itemTrash->setResourceName($resourceName);
            $itemTrash->setOptions($options);
    
            $this->objectManager->persist($itemTrash);
            $this->objectManager->flush($itemTrash);
        }

        return $itemTrash;
    }

    /**
     * Remove item from akeneo trash
     *
     * @param object $item
     */
    public function removeItemFromTrash($item)
    {
        if (!\is_object($item)) {
            throw new \Exception('Item to removed from trash should be object');
        }
        
        $resourceId = $item->getId();
        $resourceName = ClassUtils::getClass($item);
        $resource = $this->akeneoTrashRepository->findOneBy([
            'resourceId' => $resourceId,
            'resourceName' => $resourceName
            ]);
        if ($resource) {
            $this->objectManager->remove($resource);
            $this->objectManager->flush($resource);
        }
    }

    /**
    * Remove items from akeneo trash
    *
    * @param array $item
    */
    public function removeItemsFromTrash($items)
    {
        foreach ($items as $item) {
            if (!\is_object($item)) {
                throw new \Exception('Item to removed from trash should be object');
            }
            $resourceId = $item->getId();
            $resourceName = ClassUtils::getClass($item);
            $resource = $this->akeneoTrashRepository->findOneBy([
                'resourceId' => $resourceId,
                'resourceName' => $resourceName
                ]);
            if ($resource) {
                $this->objectManager->remove($resource);
                $this->objectManager->flush($resource);
            }
        }
    }

    /**
     * Restore item from akeneo trash by id
     *
     * @param array $item
     */
    public function restoreItemFromTrashById($item)
    {
        if (!isset($this->resources[$item['resource']])) {
            throw new \Exception(sprintf('No data found for resource name %s', $productId));
        }

        $resource = $this->akeneoTrashRepository->findOneBy([
            'resourceId' => $item['resourceId'],
            'resourceName' => $this->resources[$item['resource']]['resource_name']
            ]);
        if ($resource) {
            $this->objectManager->remove($resource);
            $this->objectManager->flush($resource);
        }
    }

    /**
     * Get object manager for akeneo trash
     *
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @return AkeneoTrashRepository
     */
    public function getAkeneoTrashRepository()
    {
        return $this->akeneoTrashRepository;
    }

    /**
     * Get trash resources code
     *
     * @param mixed $resourcesName
     *
     * @return array
     */
    public function getTrashResourcesCode($resourcesName)
    {
        if (!is_array($resourcesName)) {
            $resourcesName = [$resourcesName];
        }
        $codes = [];
        foreach ($resourcesName as $resourceName) {
            $codes = array_merge($codes, $this->getTrashResourceCode($resourceName));
        }

        return array_column($codes, 'code');
    }

    /**
     * Get trash resource code
     *
     * @param string
     *
     * @return array
     */
    public function getTrashResourceCode($trashResourceName)
    {
        if (!isset($this->resources[$trashResourceName])) {
            throw new \Exception(sprintf('No data found for resource name %s', $productId));
        }

        $resource = $this->resources[$trashResourceName];
        $resourceCode = $resource['code'];
        $resourceName = $resource['resource_name'];
        $resourceTable = $resource['table'];
        $qb= $this->objectManager->createQueryBuilder()
            ->select('r.'.$resourceCode.' as code')
            ->from($resourceName, 'r');

        $result = $qb->innerJoin(
            AkeneoTrash::class,
            'ktpltrash',
            'WITH',
            'ktpltrash.resourceId = r.id'
        )
            ->andWhere(
                $qb->expr()->andX(
                    $qb->expr()->eq('ktpltrash.resourceName', ':objectClass'),
                )
            )
            ->setParameter(':objectClass', $resourceName)
            ->getQuery()
            ->getResult();
        
        return $result;
    }
}
