<?php

namespace Oro\Bundle\PayPalBundle\Method\View;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\View\PaymentMethodViewInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\PayPalBundle\Form\Type\CreditCardType;
use Oro\Bundle\PayPalBundle\Method\Config\PayPalCreditCardConfigInterface;
use Oro\Bundle\PayPalBundle\PayPal\Payflow\Gateway\Option as GatewayOption;
use Oro\Bundle\PayPalBundle\PayPal\Payflow\Response\Response;
use Symfony\Component\Form\FormFactoryInterface;

class PayPalCreditCardPaymentMethodView implements PaymentMethodViewInterface
{
    /** @var FormFactoryInterface */
    protected $formFactory;

    /** @var PaymentTransactionProvider */
    protected $paymentTransactionProvider;

    /** @var PayPalCreditCardConfigInterface */
    protected $config;

    /**
     * @param FormFactoryInterface $formFactory
     * @param PayPalCreditCardConfigInterface $config
     * @param PaymentTransactionProvider $paymentTransactionProvider
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        PayPalCreditCardConfigInterface $config,
        PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->formFactory = $formFactory;
        $this->config = $config;
        $this->paymentTransactionProvider = $paymentTransactionProvider;
    }

    /** {@inheritdoc} */
    public function getOptions(PaymentContextInterface $context)
    {
        $isZeroAmountAuthorizationEnabled = $this->config->isZeroAmountAuthorizationEnabled();

        $formOptions = [
            'zeroAmountAuthorizationEnabled' => $isZeroAmountAuthorizationEnabled,
            'requireCvvEntryEnabled' => $this->config->isRequireCvvEntryEnabled(),
        ];

        $formView = $this->formFactory->create(CreditCardType::NAME, null, $formOptions)->createView();

        $viewOptions = [
            'formView' => $formView,
            'creditCardComponentOptions' => [
                'allowedCreditCards' => $this->getAllowedCreditCards(),
            ],
        ];

        if (!$isZeroAmountAuthorizationEnabled) {
            return $viewOptions;
        }

        $validateTransaction = $this->paymentTransactionProvider
            ->getActiveValidatePaymentTransaction($this->getPaymentMethodIdentifier());

        if (!$validateTransaction) {
            return $viewOptions;
        }

        $transactionOptions = $validateTransaction->getTransactionOptions();

        $viewOptions['creditCardComponent'] = 'oropaypal/js/app/components/authorized-credit-card-component';

        $viewOptions['creditCardComponentOptions'] = array_merge($viewOptions['creditCardComponentOptions'], [
            'acct' => $this->getLast4($validateTransaction),
            'saveForLaterUse' => !empty($transactionOptions['saveForLaterUse']),
        ]);

        return $viewOptions;
    }

    /**
     * @param PaymentTransaction $paymentTransaction
     * @return string|null
     */
    protected function getLast4(PaymentTransaction $paymentTransaction)
    {
        $response = new Response($paymentTransaction->getResponse());

        $acct = $response->getOffset(GatewayOption\Customer::ACCT);

        return substr($acct, -4);
    }

    /** {@inheritdoc} */
    public function getBlock()
    {
        return '_payment_methods_credit_card_widget';
    }

    /** {@inheritdoc} */
    public function getLabel()
    {
        return $this->config->getLabel();
    }

    /** {@inheritdoc} */
    public function getShortLabel()
    {
        return $this->config->getShortLabel();
    }

    /**
     * {@inheritdoc}
     */
    public function getAdminLabel()
    {
        return $this->config->getAdminLabel();
    }

    /**
     * @return array
     */
    public function getAllowedCreditCards()
    {
        return $this->config->getAllowedCreditCards();
    }

    /** {@inheritdoc} */
    public function getPaymentMethodIdentifier()
    {
        return $this->config->getPaymentMethodIdentifier();
    }
}
