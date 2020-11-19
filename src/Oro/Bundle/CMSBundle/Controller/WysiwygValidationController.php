<?php

namespace Oro\Bundle\CMSBundle\Controller;

use Oro\Bundle\CMSBundle\Tools\WYSIWYGContentChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller class with action to validate wysiwyg content.
 */
class WysiwygValidationController extends AbstractController
{
    /**
     * @Route("/", name="oro_cms_wysiwyg_validation_validate", methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function validateAction(Request $request): Response
    {
        $className = $request->get('className');
        if (!$className) {
            throw new BadRequestHttpException('ClassName field is required.');
        }

        $fieldName = $request->get('fieldName');
        if (!$fieldName) {
            throw new BadRequestHttpException('FieldName field is required.');
        }

        $errors = $this->get(WYSIWYGContentChecker::class)
            ->check((string)$request->get('content'), $className, $fieldName);

        return new JsonResponse(['success' => !$errors, 'errors' => $errors]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        return [
            WYSIWYGContentChecker::class,
        ];
    }
}
