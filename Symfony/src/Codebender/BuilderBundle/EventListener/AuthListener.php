<?php

namespace Codebender\BuilderBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthListener
{
    protected $authorizationKey;

    protected $apiVersion;

    public function __construct($authorizationKey, $apiVersion)
    {
        $this->authorizationKey = $authorizationKey;
        $this->apiVersion = $apiVersion;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        // don't execute on status action
        if ($request->get('_route') == 'CodebenderBuilderBundle_status_check') {
            return;
        }
        $providedAuthKey = $request->attributes->get('authKey');
        $providedApiVersion = $request->attributes->get('version');

        if ($providedAuthKey !== $this->authorizationKey) {
            $event->setResponse(new JsonResponse(['success' => false, 'message' => 'Invalid authorization key.']));
            return;
        }

        if ($providedApiVersion !== $this->apiVersion) {
            $event->setResponse(new JsonResponse(['success' => false, 'message' => 'Invalid api version.']));
            return;
        }
    }
}