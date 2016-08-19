<?php

namespace OroB2B\Bundle\CheckoutBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Symfony\Bridge\Doctrine\RegistryInterface;

use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;

use OroB2B\Bundle\CheckoutBundle\Entity\Checkout;
use OroB2B\Bundle\CheckoutBundle\Entity\CheckoutInterface;
use OroB2B\Bundle\CheckoutBundle\Event\CheckoutEntityEvent;
use OroB2B\Bundle\PricingBundle\Manager\UserCurrencyManager;

class CheckoutEntityListener
{
    const START_TRANSITION_DEFINITION = '__start__';

    /**
     * @var WorkflowManager
     */
    protected $workflowManager;

    /**
     * @var RegistryInterface
     */
    protected $doctrine;

    /**
     * @var string
     */
    protected $checkoutClassName;

    /**
     * @var EntityManager
     */
    protected $manager;

    /**
     * @var EntityRepository
     */
    protected $repository;

    /**
     * @var UserCurrencyManager
     */
    protected $userCurrencyManager;

    /**
     * @var array
     */
    protected $workflows = [];

    /**
     * @param WorkflowManager $workflowManager
     * @param RegistryInterface $doctrine
     * @param UserCurrencyManager $userCurrencyManager
     */
    public function __construct(
        WorkflowManager $workflowManager,
        RegistryInterface $doctrine,
        UserCurrencyManager $userCurrencyManager
    ) {
        $this->workflowManager = $workflowManager;
        $this->doctrine = $doctrine;
        $this->userCurrencyManager = $userCurrencyManager;
    }

    /**
     * @param string $checkoutClassName
     */
    public function setCheckoutClassName($checkoutClassName)
    {
        if (!is_a($checkoutClassName, 'OroB2B\Bundle\CheckoutBundle\Entity\CheckoutInterface', true)) {
            throw new \InvalidArgumentException(
                'Checkout class must implement OroB2B\Bundle\CheckoutBundle\Entity\CheckoutInterface'
            );
        }

        $this->checkoutClassName = $checkoutClassName;
    }

    /**
     * @param CheckoutEntityEvent $event
     */
    public function onCreateCheckoutEntity(CheckoutEntityEvent $event)
    {
        $this->setCheckoutToEvent($event, $this->startCheckout($event));
    }

    /**
     * @param CheckoutEntityEvent $event
     * @return null|CheckoutInterface
     */
    public function onGetCheckoutEntity(CheckoutEntityEvent $event)
    {
        $this->setCheckoutToEvent($event, $this->findExistingCheckout($event));
    }

    /**
     * @param CheckoutEntityEvent $event
     * @param CheckoutInterface|null $checkout
     */
    protected function setCheckoutToEvent(CheckoutEntityEvent $event, CheckoutInterface $checkout = null)
    {
        if ($checkout) {
            $event->setCheckoutEntity($checkout);
            $event->stopPropagation();
        }
    }

    /**
     * @param CheckoutEntityEvent $event
     * @return null|CheckoutInterface
     */
    protected function findExistingCheckout(CheckoutEntityEvent $event)
    {
        if ($event->getCheckoutId()) {
            /** @var Checkout $checkout */
            $checkout = $this->getRepository()->find($event->getCheckoutId());
        } elseif ($event->getSource() && $event->getSource()->getId()) {
            /** @var Checkout $checkout */
            $checkout = $this->getRepository()->findOneBy(['source' => $event->getSource()]);
        }

        return isset($checkout) ? $this->actualizeCheckoutCurrency($checkout) : null;
    }

    /**
     * @param Checkout $checkout
     * @return Checkout
     */
    protected function actualizeCheckoutCurrency(Checkout $checkout)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManagerForClass('OroB2BCheckoutBundle:Checkout');
        $checkout->setCurrency($this->userCurrencyManager->getUserCurrency());
        $em->persist($checkout);
        $em->flush($checkout);

        return $checkout;
    }

    /**
     * @param CheckoutEntityEvent $event
     * @return CheckoutInterface
     */
    protected function startCheckout(CheckoutEntityEvent $event)
    {
        if (!$event->getSource()) {
            return null;
        }
        
        $checkout = $this->createCheckoutEntity();
        $checkout->setSource($event->getSource());

        if (!$this->isStartWorkflowAllowed($checkout)) {
            return null;
        }

        return $checkout;
    }

    /**
     * @param $checkout
     * @return bool
     */
    protected function isStartWorkflowAllowed($checkout)
    {
        return null !== $this->getWorkflowName($checkout);
    }

    /**
     * @return string
     */
    protected function getCheckoutClassName()
    {
        return $this->checkoutClassName;
    }

    /**
     * @param CheckoutInterface $checkout
     * @return null|string
     */
    protected function getWorkflowName(CheckoutInterface $checkout)
    {
        $cacheKey = $checkout->getId();
        
        if (!array_key_exists($cacheKey, $this->workflows)) {
            $workflows = $this->workflowManager->getApplicableWorkflows($checkout);
            $workflows = array_filter(
                $workflows,
                function (Workflow $workflow) use ($checkout) {
                    return $workflow->isStartTransitionAvailable(
                        static::START_TRANSITION_DEFINITION,
                        $checkout
                    );
                }
            );

            if (count($workflows) > 1) {
                throw new \LogicException(
                    sprintf('More than one active workflow found for entity "%s".', $this->getCheckoutClassName())
                );
            }

            /** @var Workflow $workflow */
            $workflow = array_shift($workflows);
            
            $this->workflows[$cacheKey] = $workflow ? $workflow->getName() : null;
        }

        return $this->workflows[$cacheKey];
    }

    /**
     * @return CheckoutInterface
     */
    protected function createCheckoutEntity()
    {
        $checkoutClassName = $this->getCheckoutClassName();

        return new $checkoutClassName();
    }

    /**
     * @return EntityManager|null
     */
    protected function getManager()
    {
        if (!$this->manager) {
            $this->manager = $this->doctrine->getManagerForClass($this->getCheckoutClassName());
        }
        return $this->manager;
    }

    /**
     * @return EntityRepository
     */
    protected function getRepository()
    {
        if (!$this->repository) {
            $this->repository = $this->getManager()->getRepository($this->getCheckoutClassName());
        }

        return $this->repository;
    }
}
