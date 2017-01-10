<?php

namespace Oro\Bundle\CustomerBundle\Controller;

use Symfony\Component\Routing\Annotation\Route;

use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;

class AjaxAccountUserController extends AbstractAjaxAccountUserController
{
    /**
     * @Route("/get-account/{id}",
     *      name="oro_customer_account_user_get_account",
     *      requirements={"id"="\d+"}
     * )
     * @AclAncestor("oro_account_account_user_view")
     *
     * {@inheritdoc}
     */
    public function getAccountIdAction(CustomerUser $accountUser)
    {
        return parent::getAccountIdAction($accountUser);
    }
}
