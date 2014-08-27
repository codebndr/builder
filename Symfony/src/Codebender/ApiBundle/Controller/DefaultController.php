<?php

namespace Codebender\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * default controller of api bundle
 */
class DefaultController extends Controller
{
    /**
     * index action works as a homepage
     *
     * @return Response Response intance.
     */
    public function indexAction()
    {
        return $this->render('CodebenderApiBundle:Default:index.html.twig');
    }
}
