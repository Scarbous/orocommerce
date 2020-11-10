<?php

namespace Oro\Bundle\ShoppingListBundle\Layout\DataProvider;

use Doctrine\Common\Collections\Criteria;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Manager\CurrentShoppingListManager;
use Oro\Bundle\ShoppingListBundle\Manager\ShoppingListTotalManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides currently logged in user shopping lists for layouts.
 */
class CustomerUserShoppingListsProvider
{
    const DATA_SORT_BY_UPDATED = 'updated';

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ShoppingListTotalManager
     */
    protected $totalManager;

    /**
     * @var CurrentShoppingListManager
     */
    protected $currentShoppingListManager;

    /**
     * @var ConfigManager|null
     */
    private $configManager;

    /**
     * @var TokenAccessorInterface|null
     */
    private $tokenAccessor;

    /**
     * @var boolean|null
     */
    private $isShowAllShoppingLists;

    /**
     * @param RequestStack $requestStack
     * @param ShoppingListTotalManager $totalManager
     * @param CurrentShoppingListManager $currentShoppingListManager
     */
    public function __construct(
        RequestStack $requestStack,
        ShoppingListTotalManager $totalManager,
        CurrentShoppingListManager $currentShoppingListManager
    ) {
        $this->requestStack = $requestStack;
        $this->totalManager = $totalManager;
        $this->currentShoppingListManager = $currentShoppingListManager;
    }

    /**
     * @param ConfigManager|null $configManager
     */
    public function setConfigManager(?ConfigManager $configManager): void
    {
        $this->configManager = $configManager;
    }

    /**
     * @param TokenAccessorInterface|null $tokenAccessor
     */
    public function setTokenAccessor(?TokenAccessorInterface $tokenAccessor): void
    {
        $this->tokenAccessor = $tokenAccessor;
    }

    /**
     * @return ShoppingList
     */
    public function getCurrent()
    {
        return $this->currentShoppingListManager->getCurrent();
    }

    /**
     * @param ShoppingList $shoppingList
     * @return bool
     */
    public function isCurrent(ShoppingList $shoppingList): bool
    {
        $current = $this->getCurrent();

        return $current && $current->getId() === $shoppingList->getId();
    }

    /**
     * @return ShoppingList[]
     */
    public function getShoppingLists()
    {
        if (!array_key_exists('shoppingLists', $this->options)) {
            $shoppingLists = $this->currentShoppingListManager
                ->getShoppingListsWithCurrentFirst($this->getSortOrder());
            $this->totalManager->setSubtotals($shoppingLists, false);
            $this->options['shoppingLists'] = $shoppingLists;
        }

        return $this->options['shoppingLists'];
    }

    /**
     * @return ShoppingList[]
     */
    public function getShoppingListsForWidget(): array
    {
        $customerUserId = $this->getCustomerUserId();
        if (!$customerUserId || $this->isShowAllInShoppingListWidget()) {
            return $this->getShoppingLists();
        }

        if (!array_key_exists('shoppingListsForWidget', $this->options)) {
            $shoppingLists = $this->currentShoppingListManager->getShoppingListsForCustomerUserWithCurrentFirst(
                $customerUserId,
                $this->getSortOrder()
            );

            if ($shoppingLists) {
                $this->totalManager->setSubtotals($shoppingLists, false);
            }

            $this->options['shoppingListsForWidget'] = $shoppingLists;
        }

        return $this->options['shoppingListsForWidget'];
    }

    /**
     * @return int|null
     */
    private function getCustomerUserId(): ?int
    {
        return $this->tokenAccessor ? $this->tokenAccessor->getUserId() : null;
    }

    /**
     * @return bool
     */
    private function isShowAllInShoppingListWidget(): bool
    {
        if ($this->configManager && $this->isShowAllShoppingLists === null) {
            $this->isShowAllShoppingLists = $this->configManager
                ->get('oro_shopping_list.show_all_in_shopping_list_widget');
        }

        return !$this->configManager || $this->isShowAllShoppingLists;
    }

    /**
     * @return string[]
     */
    protected function getSortOrder()
    {
        $request = $this->requestStack->getCurrentRequest();
        $sort = $request ? $request->get('shopping_list_sort') : self::DATA_SORT_BY_UPDATED;

        switch ($sort) {
            case self::DATA_SORT_BY_UPDATED:
                $sortOrder['list.updatedAt'] = Criteria::DESC;
                break;
            default:
                $sortOrder['list.id'] = Criteria::ASC;
        }

        return $sortOrder;
    }
}
