<?php

namespace KTPL\AkeneoTrashBundle\Datasource;

use Akeneo\UserManagement\Bundle\Context\UserContext;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\PimDataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Oro\Bundle\PimDataGridBundle\Datasource\ParameterizableInterface;
use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface;
use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CategoryTrashDatasource implements
    DatasourceInterface,
    ParameterizableInterface
{
    /** @var DatagridRepositoryInterface */
    protected $repository;

    /** @var MassActionRepositoryInterface */
    protected $massRepository;

    /** @var QueryBuilder */
    protected $qb;

    /** @var HydratorInterface */
    protected $hydrator;

    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var UserContext */
    protected $userContext;

    /** @var array */
    protected $parameters = [];

    /**
     * @param DatagridRepositoryInterface   $repository
     * @param MassActionRepositoryInterface $massRepository
     * @param HydratorInterface             $hydrator
     * @param NormalizerInterface           $normalizer
     * @param UserContext                   $userContext
     */
    public function __construct(
        DatagridRepositoryInterface $repository,
        MassActionRepositoryInterface $massRepository,
        HydratorInterface $hydrator,
        NormalizerInterface $normalizer,
        UserContext $userContext
    ) {
        $this->repository = $repository;
        $this->massRepository = $massRepository;
        $this->hydrator = $hydrator;
        $this->normalizer = $normalizer;
        $this->userContext = $userContext;
    }

    /**
     * {@inheritdoc}
     */
    public function process(DatagridInterface $grid, array $config)
    {
        $this->qb = $this->repository->createDatagridQueryBuilder();
        $grid->setDatasource(clone $this);
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters($parameters)
    {
        $this->parameters += $parameters;

        if ($this->qb instanceof QueryBuilder) {
            $this->qb->setParameters($this->parameters);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        $categories = $this->qb->getQuery()->execute();

        return array_map(function ($category) {
            return new ResultRecord(
                $this->normalizer->normalize(
                    $category,
                    'datagrid',
                    ['localeCode' => isset($this->getParameters()[':localeCode']) ?
                        $this->getParameters()[':localeCode'] :
                        $this->userContext->getCurrentLocaleCode()
                    ]
                )
            );
        }, $categories);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository()
    {
        throw new \LogicException("No need to implement this method, design flaw in interface!");
    }

    /**
     * {@inheritdoc}
     */
    public function getMassActionRepository()
    {
        return $this->massRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function setMassActionRepository(MassActionRepositoryInterface $massActionRepository)
    {
        throw new \LogicException("No need to implement this method, design flaw in interface!");
    }

    /**
     * {@inheritdoc}
     */
    public function setHydrator(HydratorInterface $hydrator)
    {
        $this->hydrator = $hydrator;
    }
}
