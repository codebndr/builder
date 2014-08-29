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
     * compilelibraries action
     *
     * @return Response Response intance.
     *
     */
    public function compilelibrariesAction($auth_key, $version)
    {

        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "step" => 0, "message" => "Invalid authorization key.")));
        }

        if ($version == $this->container->getParameter('version')) {
            $request = $this->getRequest()->getContent();

            $contents = json_decode($request, true);

            $apihandler = $this->get('codebender_api.handler');

            $personalMatchedLib = $contents['libraries'];
            $files = $contents["files"];

            // get library manager url.
            $libmanager_url = $this->container->getParameter('library');

            $headersArr = $this->checkHeaders($files, $personalMatchedLib);

            $contents["libraries"] = $headersArr['libraries'];
            $request_content = json_encode($contents);

            // compile into compiler with passing request_contents data.
            $data = $apihandler->post_raw_data($this->container->getParameter('compiler'), $request_content);

            $responsedata = array('success' => true, 'library' => $headersArr['libraries'], 'foundFiles' => $headersArr['foundFiles'], 'notFoundHeaders' => $headersArr['notFoundHeaders'], 'compileResponse' => $data);

            return new Response(json_encode($responsedata), 200);
        }
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

