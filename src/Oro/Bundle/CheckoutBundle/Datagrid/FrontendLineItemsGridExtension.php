<?php

namespace Oro\Bundle\CheckoutBundle\Datagrid;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Common\MetadataObject;
use Oro\Bundle\DataGridBundle\Datagrid\Common\ResultsObject;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmQueryConfiguration;
use Oro\Bundle\DataGridBundle\Extension\AbstractExtension;

/**
 * Adds additional configuration and metadata to the grid.
 */
class FrontendLineItemsGridExtension extends AbstractExtension
{
    /** @var string[] */
    private const SUPPORTED_GRIDS = [
        'frontend-checkout-line-items-grid',
        'frontend-single-page-checkout-line-items-grid',
    ];

    /** @var ManagerRegistry */
    private $registry;

    /** @var ConfigManager */
    private $configManager;

    /** @var array */
    private $cache = [];

    /**
     * @param ManagerRegistry $registry
     * @param ConfigManager $configManager
     */
    public function __construct(ManagerRegistry $registry, ConfigManager $configManager)
    {
        $this->registry = $registry;
        $this->configManager = $configManager;
    }

    /**
     * {@inheritdoc}
     */
    public function isApplicable(DatagridConfiguration $config): bool
    {
        return \in_array($config->getName(), static::SUPPORTED_GRIDS, true) && parent::isApplicable($config);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(ParameterBag $parameters): void
    {
        if ($parameters->has(ParameterBag::MINIFIED_PARAMETERS)) {
            $minifiedParameters = $parameters->get(ParameterBag::MINIFIED_PARAMETERS);
            $additional = $parameters->get(ParameterBag::ADDITIONAL_PARAMETERS, []);

            if (array_key_exists('g', $minifiedParameters)) {
                $additional['group'] = $minifiedParameters['g']['group'] ?? false;
            }

            $parameters->set(ParameterBag::ADDITIONAL_PARAMETERS, $additional);
        }

        parent::setParameters($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function processConfigs(DatagridConfiguration $config): void
    {
        $queryPart = 'lineItem.id';
        if ($this->isLineItemsGrouped()) {
            $queryPart = '(SELECT GROUP_CONCAT(innerItem.id ORDER BY innerItem.id ASC) ' .
                'FROM Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem innerItem ' .
                'WHERE (innerItem.parentProduct = lineItem.parentProduct OR innerItem.product = lineItem.product) ' .
                'AND innerItem.checkout = lineItem.checkout ' .
                'AND innerItem.productUnit = lineItem.productUnit) as allLineItemsIds';
        }
        $config->offsetAddToArrayByPath(OrmQueryConfiguration::SELECT_PATH, [$queryPart]);

        $checkoutId = $this->getCheckoutId();
        if (!$checkoutId) {
            return;
        }

        $checkout = $this->getCheckout($checkoutId);
        $lineItemsCount = $checkout ? count($checkout->getLineItems()) : 0;
        $item = $this->configManager->get('oro_checkout.checkout_max_line_items_per_page');
        if ($lineItemsCount <= $item) {
            $item = [
                'label' => 'oro.checkout.grid.toolbar.pageSize.all.label',
                'size' => $item
            ];
        }

        $config->offsetSetByPath(
            '[options][toolbarOptions][pageSize][items]',
            array_merge(
                $config->offsetGetByPath('[options][toolbarOptions][pageSize][items]'),
                [$item]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function visitMetadata(DatagridConfiguration $config, MetadataObject $data): void
    {
        $checkoutId = $this->getCheckoutId();
        if (!$checkoutId) {
            return;
        }

        $data->offsetSetByPath('[canBeGrouped]', $this->canBeGrouped($checkoutId));
        $data->offsetAddToArrayByPath('[initialState][parameters]', ['group' => false]);
        $data->offsetAddToArrayByPath('[state][parameters]', ['group' => $this->isLineItemsGrouped()]);
    }

    /**
     * {@inheritdoc}
     */
    public function visitResult(DatagridConfiguration $config, ResultsObject $result): void
    {
        $checkoutId = $this->getCheckoutId();
        if (!$checkoutId) {
            return;
        }

        $result->offsetAddToArrayByPath(
            '[metadata]',
            [
                'canBeGrouped' => $this->canBeGrouped($checkoutId),
            ]
        );
    }

    /**
     * @return int
     */
    private function getCheckoutId(): int
    {
        return (int) $this->parameters->get('checkout_id');
    }

    /**
     * @return bool
     */
    private function isLineItemsGrouped(): bool
    {
        $parameters = $this->parameters->get('_parameters', []);

        return isset($parameters['group']) ? filter_var($parameters['group'], FILTER_VALIDATE_BOOLEAN) : false;
    }

    /**
     * @param int $checkoutId
     * @return bool
     */
    private function canBeGrouped(int $checkoutId): bool
    {
        if (!isset($this->cache['canBeGrouped'][$checkoutId])) {
            $this->cache['canBeGrouped'][$checkoutId] = $this->registry
                ->getManagerForClass(CheckoutLineItem::class)
                ->getRepository(CheckoutLineItem::class)
                ->canBeGrouped($checkoutId);
        }

        return $this->cache['canBeGrouped'][$checkoutId];
    }

    /**
     * @param int $checkoutId
     * @return Checkout|null
     */
    private function getCheckout(int $checkoutId): ?Checkout
    {
        if (!isset($this->cache['checkouts'][$checkoutId])) {
            $this->cache['checkouts'][$checkoutId] = $this->registry
                ->getManagerForClass(Checkout::class)
                ->getRepository(Checkout::class)
                ->find($checkoutId);
        }

        return $this->cache['checkouts'][$checkoutId];
    }
}
