<?php

namespace OroB2B\Bundle\InvoiceBundle\EventListener\ORM;

use Doctrine\ORM\Event\LifecycleEventArgs;

use OroB2B\Bundle\InvoiceBundle\Doctrine\ORM\InvoiceNumberGeneratorInterface;
use OroB2B\Bundle\InvoiceBundle\Entity\Invoice;

class InvoiceEventListener
{
    /**
     * @var InvoiceNumberGeneratorInterface
     */
    private $invoiceNumberGenerator;

    /**
     * @param InvoiceNumberGeneratorInterface $numberGenerator
     * @return $this
     */
    public function setInvoiceNumberGenerator(InvoiceNumberGeneratorInterface $numberGenerator)
    {
        $this->invoiceNumberGenerator = $numberGenerator;

        return $this;
    }

    /**
     * @param Invoice $invoice
     */
    public function prePersist(Invoice $invoice)
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $invoice->setCreatedAt($now);
        $invoice->setUpdatedAt($now);
    }

    /**
     * @param Invoice $invoice
     */
    public function preUpdate(Invoice $invoice)
    {
        $invoice->setUpdatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
    }

    /**
     * @param Invoice $invoice
     * @param LifecycleEventArgs $event
     *
     * Persist invoiceNumber based on entity id
     */
    public function postPersist(Invoice $invoice, LifecycleEventArgs $event)
    {
        if (null === $invoice->getInvoiceNumber()) {
            $changeSet = [
                'invoiceNumber' => [null, $this->invoiceNumberGenerator->generate($invoice)],
            ];

            $unitOfWork = $event->getEntityManager()->getUnitOfWork();
            $unitOfWork->scheduleExtraUpdate($invoice, $changeSet);
        }
    }
}
