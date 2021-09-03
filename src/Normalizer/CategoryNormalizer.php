<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Normalizer;

use Akeneo\Pim\Enrichment\Component\Category\Model\CategoryInterface;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalize category for the category trash grid
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CategoryNormalizer implements NormalizerInterface
{
    /** @var NormalizerInterface */
    private $translationNormalizer;
    
    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /**
     * @param NormalizerInterface $translationNormalizer
     */
    public function __construct(
        NormalizerInterface $translationNormalizer,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->translationNormalizer = $translationNormalizer;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($category, $format = null, array $context = array()): array
    {
        $category = isset($category[0]) ? $category[0] : $category;
        $labels = $this->translationNormalizer->normalize($category, 'standard', $context);
        $parentLabels = $category->getParent() ? $this->translationNormalizer->normalize($category->getParent(), 'standard', $context) : [];
        $parentLabel = $category->getParent() ? $category->getParent()->getCode() : '';
        $rootCategory = $category->getRoot() ? $this->categoryRepository->find($category->getRoot()) : null;
        $rootLabels = [];
        $rootLabel = '';

        if ($rootCategory) {
            $rootLabels = $this->translationNormalizer->normalize($rootCategory, 'standard', $context);
            $rootLabel =  isset($context['localeCode']) && isset($rootLabels[$context['localeCode']]) ?
            $rootLabels[$context['localeCode']] : $rootCategory->getCode();
        }

        $normalizedCategory = [
            'id'   => $category->getId(),
            'code' => $category->getCode(),
            'parent_label' => isset($context['localeCode']) && isset($parentLabels[$context['localeCode']]) ?
            $parentLabels[$context['localeCode']] : $parentLabel,
            'label'  => isset($context['localeCode']) && isset($labels[$context['localeCode']]) ?
                $labels[$context['localeCode']] : $category->getCode(),
            'root_label' => $rootLabel,
        ];

        return $normalizedCategory;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof CategoryInterface && 'datagrid' === $format;
    }
}
