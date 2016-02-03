<?php

namespace Codebender\BuilderBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataValidationListener
{
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        // don't execute on status action
        if ($request->get('_route') == 'CodebenderBuilderBundle_status_check') {
            return;
        }
        $requestContent = json_decode($request->getContent(), true);
        if ($requestContent === null || json_last_error() != JSON_ERROR_NONE) {
            $event->setResponse(new JsonResponse(['success' => false, 'message' => 'Invalid input.']));
        }
    }
}