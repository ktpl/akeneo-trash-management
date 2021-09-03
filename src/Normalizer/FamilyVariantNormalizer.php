<?php

declare(strict_types=1);

namespace KTPL\AkeneoTrashBundle\Normalizer;

use Akeneo\UserManagement\Bundle\Context\UserContext;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Oro\Bundle\PimDataGridBundle\Normalizer\FamilyVariantNormalizer as BaseFamilyVariantNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalize family variant for the family variant grid
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class FamilyVariantNormalizer extends BaseFamilyVariantNormalizer
{
    /** @var NormalizerInterface */
    private $translationNormalizer;

    /** @var UserContext */
    private $userContext;

    /**
     * @param NormalizerInterface $translationNormalizer
     * @param UserContext         $userContext
     */
    public function __construct(
        NormalizerInterface $translationNormalizer,
        UserContext $userContext
    ) {
        parent::__construct($translationNormalizer);

        $this->translationNormalizer = $translationNormalizer;
        $this->userContext = $userContext;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($familyVariant, $format = null, array $context = array()): array
    {
        $normalizedFamilyVariant = parent::normalize($familyVariant, $format, $context);

        $localeCode = $this->userContext->getCurrentLocaleCode();
        if (!empty($context['localeCode'])) {
            $localeCode = $context['localeCode'];
        }

        $family = $familyVariant->getFamily();
        $familyLabels = $this->translationNormalizer->normalize($family, 'standard', $context);
        $familyLabel = isset($familyLabels[$localeCode]) ? $familyLabels[$localeCode] : $family->getCode();

        $normalizedFamilyVariant['familyLabel'] = $familyLabel;

        return $normalizedFamilyVariant;
    }
}
