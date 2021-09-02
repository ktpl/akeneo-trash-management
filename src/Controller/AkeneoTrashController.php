<?php

namespace KTPL\AkeneoTrashBundle\Controller;

use Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface;
use Akeneo\Pim\Enrichment\Component\Category\Model\CategoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductModelRepositoryInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface;
use Akeneo\Pim\Structure\Component\Repository\FamilyVariantRepositoryInterface;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use KTPL\AkeneoTrashBundle\Manager\AkeneoTrashManager;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Akeneo trash controller
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class AkeneoTrashController
{
    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var ProductModelRepositoryInterface */
    private $productModelRepository;

    /** @var RemoverInterface */
    private $productRemover;

    /** @var RemoverInterface */
    private $productModelRemover;

    /** @var Client */
    private $productAndProductModelClient;

    /** @var ObjectFilterInterface */
    private $objectFilter;

    /** @var AkeneoTrashManager */
    private $akeneoTrashManager;

    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /** @var RemoverInterface */
    private $categoryRemover;

    /** @var FamilyRepositoryInterface */
    private $familyRepository;

    /** @var RemoverInterface */
    private $familyRemover;

    /** @var FamilyVariantRepositoryInterface */
    private $familyVariantRepository;
    
    /** @var RemoverInterface */
    private $familyVariantRemover;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductModelRepositoryInterface $productModelRepository,
        RemoverInterface $productRemover,
        RemoverInterface $productModelRemover,
        Client $productAndProductModelClient,
        ObjectFilterInterface $objectFilter,
        AkeneoTrashManager $akeneoTrashManager,
        CategoryRepositoryInterface $categoryRepository,
        RemoverInterface $categoryRemover,
        FamilyRepositoryInterface $familyRepository,
        RemoverInterface $familyRemover,
        FamilyVariantRepositoryInterface $familyVariantRepository,
        RemoverInterface $familyVariantRemover
    ) {
        $this->productRepository = $productRepository;
        $this->productModelRepository = $productModelRepository;
        $this->productRemover = $productRemover;
        $this->productModelRemover = $productModelRemover;
        $this->productAndProductModelClient = $productAndProductModelClient;
        $this->objectFilter = $objectFilter;
        $this->akeneoTrashManager = $akeneoTrashManager;
        $this->categoryRepository = $categoryRepository;
        $this->categoryRemover = $categoryRemover;
        $this->familyRepository = $familyRepository;
        $this->familyRemover = $familyRemover;
        $this->familyVariantRepository = $familyVariantRepository;
        $this->familyVariantRemover = $familyVariantRemover;
    }

    /**
     * Remove product
     *
     * @param Request $request
     * @param int     $id
     *
     * @AclAncestor("ktpl_akeneo_trash_remove_product")
     *
     * @return JsonResponse
     */
    public function removeProductAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        $product = $this->findProductOr404($id);
        $this->productRemover->remove($product);

        $this->productAndProductModelClient->refreshIndex();

        return new JsonResponse();
    }

    /**
     * Remove product model
     *
     * @param Request $request
     * @param int     $id
     *
     * @AclAncestor("ktpl_akeneo_trash_remove_product")
     *
     * @return JsonResponse
     */
    public function removeProductModelAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        $product = $this->findProductModelOr404($id);
        $this->productModelRemover->remove($product);

        $this->productAndProductModelClient->refreshIndex();

        return new JsonResponse();
    }

    /**
     * Remove category
     *
     * @param Request $request
     * @param int     $id
     *
     * @AclAncestor("ktpl_akeneo_trash_remove_category")
     *
     * @return JsonResponse
     */
    public function removeCategoryAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        $category = $this->findCategoryOr404($id);

        $this->categoryRemover->remove($category);

        return new JsonResponse();
    }

    /**
     * Removes given family
     *
     * @AclAncestor("ktpl_akeneo_trash_remove_family")
     *
     * @param Request $request
     * @param int     $id
     *
     * @return Response
     */
    public function removeFamilyAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        $family = $this->getFamily($id);

        try {
            $this->familyRemover->remove($family);
        } catch (\LogicException $e) {
            return new JsonResponse(
                [
                    'message' => $e->getMessage(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param Request $request
     * @param int     $id
     *
     * @return JsonResponse
     *
     * @AclAncestor("ktpl_akeneo_trash_remove_family_variant")
     */
    public function removeFamilyVariantAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'An error occurred.', 'global' => true], Response::HTTP_BAD_REQUEST);
        }

        $familyVariant = $this->getFamilyVariant($id);
        try {
            $this->familyVariantRemover->remove($familyVariant);
        } catch (\LogicException $e) {
            return new JsonResponse(
                [
                    'message' => sprintf(
                        'Cannot remove family variant "%s" as it is used by some product models',
                        $familyVariant->getCode()
                    ),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Restore item from trash
     *
     * @param Request $request
     * @param string  $resource
     * @param int     $id
     *
     * @AclAncestor("ktpl_akeneo_trash_restore_trash")
     *
     * @return JsonResponse
     */
    public function restoreTrashAction(Request $request, $resource, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }
        
        $this->akeneoTrashManager->restoreItemFromTrashById([
            'resourceId' => $id,
            'resource' => $resource
        ]);

        return new JsonResponse();
    }

    /**
     * Find a product by its id or return a 404 response
     *
     * @param string $id the product id
     *
     * @throws NotFoundHttpException
     *
     * @return ProductInterface
     */
    protected function findProductOr404($id)
    {
        $product = $this->productRepository->find($id);

        if (null === $product) {
            throw new NotFoundHttpException(
                sprintf('Product with id %s could not be found.', $id)
            );
        }

        return $product;
    }

    /**
     * Find a product model by its id or throw a 404
     *
     * @param string $id the product id
     *
     * @throws NotFoundHttpException
     *
     * @return ProductModelInterface
     */
    protected function findProductModelOr404($id): ProductModelInterface
    {
        $productModel = $this->productModelRepository->find($id);
        $productModel = $this->objectFilter->filterObject($productModel, 'pim.internal_api.product.view') ? null : $productModel;

        if (null === $productModel) {
            throw new NotFoundHttpException(
                sprintf('ProductModel with id %s could not be found.', $id)
            );
        }

        return $productModel;
    }

    /**
     * Find a category by its id or return a 404 response
     *
     * @param string $id the category id
     *
     * @throws NotFoundHttpException
     *
     * @return CategoryInterface
     */
    protected function findCategoryOr404($id)
    {
        $category = $this->categoryRepository->find($id);

        if (null === $category) {
            throw new NotFoundHttpException(
                sprintf('Category with id %s could not be found.', $id)
            );
        }

        return $category;
    }

    /**
     * Gets family
     *
     * @param int $id
     *
     * @throws NotFoundHttpException
     *
     * @return FamilyInterface
     */
    protected function getFamily($id): FamilyInterface
    {
        $family = $this->familyRepository->find($id);

        if (null === $family) {
            throw new NotFoundHttpException(
                sprintf('Family with id %s does not exist.', $id)
            );
        }

        return $family;
    }

    /**
     * Gets familyVariant using its id
     *
     * @param int $id
     *
     * @return FamilyVariantInterface
     */
    protected function getFamilyVariant($id): FamilyVariantInterface
    {
        $familyVariant = $this->familyVariantRepository->find($id);

        if (null === $familyVariant) {
            throw new NotFoundHttpException(
                sprintf('Family variant with id %s does not exist.', $id)
            );
        }

        return $familyVariant;
    }
}
