<?php

namespace Codebender\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * default controller of api bundle
 */
class DefaultController extends Controller
{
    /**
     * status action
     *
     * @return Response Response intance.
     *
     */
    public function statusAction()
    {
        return new Response(json_encode(array("success" => true, "status" => "OK")));
    }

    /**
     * compileWebsite action
     *
     * @return Response Response intance.
     *
     */
    public function compileWebsiteAction($auth_key, $version)
    {

        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        if ($version !== $this->container->getParameter('version'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid api version.")));
        }

        $request = $this->getRequest()->getContent();

        $contents = json_decode($request, true);

        $apihandler = $this->get('codebender_api.handler');

        $personalMatchedLibs = $contents['libraries'];

        $files = $contents["files"];

        $headersArr = $this->checkHeaders($files, $personalMatchedLibs);

        $contents["libraries"] = $headersArr['libraries'];
        $request_content = json_encode($contents);

        // perform the actual post to the compiler
        $data = $apihandler->post_raw_data($this->container->getParameter('compiler'), $request_content);

        $responsedata = array('success' => true, 'personal' => $personalMatchedLibs,  'library' => $headersArr['libraries'], 'foundFiles' => $headersArr['foundFiles'], 'notFoundHeaders' => $headersArr['notFoundHeaders'], 'compileResponse' => $data);

        return new Response(json_encode($responsedata));
    }

    /**
     * compile action
     *
     * @return Response Response intance.
     *
     */
    public function compileAction($auth_key, $version)
    {

        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        if ($version !== $this->container->getParameter('version'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid api version.")));
        }

        $request = $this->getRequest()->getContent();

        $contents = json_decode($request, true);

        $apihandler = $this->get('codebender_api.handler');

        $files = $contents["files"];

        $headersArr = $this->checkHeaders($files, array());

        $contents["libraries"] = $headersArr['libraries'];
        $request_content = json_encode($contents);

        // perform the actual post to the compiler
        $data = $apihandler->post_raw_data($this->container->getParameter('compiler'), $request_content);

        return new Response($data);
    }

    /**
     *
     * @param type $files
     * @param array $personalLibs
     *
     * @return type
     */
    protected function checkHeaders($files,  array $personalLibs)
    {
        $apiHandler = $this->get('codebender_api.handler');

        $headers = $apiHandler->read_libraries($files);

        // declare arrays
        $libraries = $notFoundHeaders = $foundFiles = array();

        // get library manager url
        $libmanager_url = $this->container->getParameter('library');

        $libraries = $personalLibs;
        foreach ($headers as $header) {
            if (!in_array($header, $personalLibs)) {

                $data = $apiHandler->get($libmanager_url . "/fetch?library=" . urlencode($header));
                $data = json_decode($data, true);

                if ($data["success"]) {
                    $libraries[$header] = $data["files"];
                    foreach ($data['files'] as $file) {
                        $foundFiles[] = $file['filename'];
                    }
                } else {
                    $notFoundHeaders[] = $header . ".h";
                }
            }
        }
        return array('libraries' => $libraries, 'foundFiles' => $foundFiles, 'notFoundHeaders' => $notFoundHeaders);
    }
}

