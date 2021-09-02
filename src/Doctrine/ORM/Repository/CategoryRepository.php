<?php

namespace KTPL\AkeneoTrashBundle\Doctrine\ORM\Repository;

use Akeneo\Tool\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository as BaseCategoryRepository;
use Akeneo\Tool\Component\Classification\Model\CategoryInterface;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;

/**
 * Category repository
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CategoryRepository extends BaseCategoryRepository
{
    const CATEGORY_RESOURCE_NAME = 'category';

    /** @var AkeneoTrashManager*/
    protected $akeneoTrashManager;

    /**
     * Set akeneo trash manager service
     *
     * @param AkeneoTrashManager $akeneoTrashManager
     */
    public function setAkeneoTrashManager(AkeneoTrashManager $akeneoTrashManager)
    {
        $this->akeneoTrashManager = $akeneoTrashManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getCategoriesByIds(array $categoriesIds = [])
    {
        if (empty($categoriesIds)) {
            return new ArrayCollection();
        }

        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();
        $categoriesIds = array_diff($categoriesIds, $categoriesCodesToExclude);
        
        $meta = $this->getClassMetadata();
        $config = $this->listener->getConfiguration($this->_em, $meta->name);

        $qb = $this->_em->createQueryBuilder();
        $qb->select('node')
            ->from($config['useObjectClass'], 'node')
            ->where('node.id IN (:categoriesIds)');

        $qb->setParameter('categoriesIds', $categoriesIds);

        $result = $qb->getQuery()->getResult();
        $result = new ArrayCollection($result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getCategoryIdsByCodes(array $categoriesCodes)
    {
        if (empty($categoriesCodes)) {
            return [];
        }

        $meta = $this->getClassMetadata();
        $config = $this->listener->getConfiguration($this->_em, $meta->name);

        $qb = $this->_em->createQueryBuilder();
        $qb->select('node.id')
            ->from($config['useObjectClass'], 'node')
            ->where('node.code IN (:categoriesCodes)');

        $qb->setParameter('categoriesCodes', $categoriesCodes);

        $categories = $qb->getQuery()->execute(null, AbstractQuery::HYDRATE_SCALAR);
        $ids = [];
        foreach ($categories as $category) {
            $ids[] = (int) $category['id'];
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     */
    public function getTreeFromParents(array $parentsIds)
    {
        if (count($parentsIds) === 0) {
            return [];
        }

        $meta = $this->getClassMetadata();
        $config = $this->listener->getConfiguration($this->_em, $meta->name);
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();

        $qb = $this->_em->createQueryBuilder();
        $qb->select('node')
            ->from($config['useObjectClass'], 'node')
            ->where('node.id IN (:parentsIds) OR node.parent IN (:parentsIds)')
            ->andWhere('node.code NOT IN (:categoriesCodesToExclude)')
            ->orderBy('node.left');

        $qb->setParameter('parentsIds', $parentsIds)
            ->setParameter('categoriesCodesToExclude', $categoriesCodesToExclude);

        
        $nodes = $qb->getQuery()->getResult();

        return $this->buildTreeNode($nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function getChildrenGrantedByParentId(CategoryInterface $parent, array $grantedCategoryIds = [])
    {
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();
        $grantedCategoryIds = array_diff($grantedCategoryIds, $categoriesCodesToExclude);

        return $this->getChildrenQueryBuilder($parent, true)
            ->andWhere('node.id IN (:ids)')
            ->setParameter('ids', $grantedCategoryIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getChildrenTreeByParentId($parentId, $selectNodeId = false, array $grantedCategoryIds = [])
    {
        $children = [];
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();
        $grantedCategoryIds = array_diff($grantedCategoryIds, $categoriesCodesToExclude);
        
        if ($selectNodeId === false) {
            $parent = $this->find($parentId);
            $children = $this->childrenHierarchy($parent);
        } else {
            $selectNode = $this->find($selectNodeId);
            if ($selectNode != null) {
                $meta = $this->getClassMetadata();
                $config = $this->listener->getConfiguration($this->_em, $meta->name);

                $selectPath = $this->getPath($selectNode);
                $parent = $this->find($parentId);
                $qb = $this->getNodesHierarchyQueryBuilder($parent);

                // Remove the node itself from his ancestor
                array_pop($selectPath);

                $ancestorsIds = [];

                foreach ($selectPath as $ancestor) {
                    $ancestorsIds[] = $ancestor->getId();
                }

                $qb->andWhere(
                    $qb->expr()->in('node.' . $config['parent'], $ancestorsIds)
                );

                if (!empty($grantedCategoryIds)) {
                    $qb->andWhere('node.id IN (:ids)')
                        ->setParameter('ids', $grantedCategoryIds);
                }

                $nodes = $qb->getQuery()->getResult();
                $children = $this->buildTreeNode($nodes);
            }
        }

        return $children;
    }

    /**
     * {@inheritdoc}
     */
    public function getGrantedTrees(array $grantedCategoryIds = [])
    {
        $qb = $this->getChildrenQueryBuilder(null, true, 'created', 'DESC');
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();
        $grantedCategoryIds = array_diff($grantedCategoryIds, $categoriesCodesToExclude);
        
        $result = $qb
            ->andWhere('node.id IN (:ids)')
            ->setParameter('ids', $grantedCategoryIds)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        $childrens = parent::getChildren($node, $direct, $sortByField, $direction, $includeNode);
        $categoriesCodesToExclude = $this->getAllTrashCategoriesCodes();

        foreach ($childrens as $childrenKey => $children) {
            if (in_array($children->getCode(), $categoriesCodesToExclude)) {
                unset($childrens[$childrenKey]);
            }
        }
        
        return array_values($childrens);
    }

    /**
     * Return categories codes of the trash categories
     *
     * @return array
     */
    protected function getAllTrashCategoriesCodes()
    {
        return $this->akeneoTrashManager->getTrashResourcesCode(
            [
                self::CATEGORY_RESOURCE_NAME
            ]
        );
    }
}
