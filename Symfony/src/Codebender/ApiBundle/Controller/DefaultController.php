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
     * compile action
     *
     * @return Response Response intance.
     *
     */
    public function compileAction($auth_key, $version)
    {

        if ($auth_key !== $this->container->getParameter('auth_key'))
        {
            return new Response(json_encode(array("success" => false, "message" => "Invalid authorization key.")));
        }

        if ($version !== $this->container->getParameter('version'))
        {
            return new Response(json_encode(array("success" => false, "message" => "Invalid api version.")));
        }

        $request = $this->getRequest()->getContent();
        if (empty($request))
        {
            return new Response(json_encode(array("success" => false, "message" => "Invalid input.")));
        }

        $contents = json_decode($request, true);

        $apihandler = $this->get('codebender_api.handler');

        $files = $contents["files"];

        $this->checkForUserProject($files);

        $userlibs = array();

        if (array_key_exists('libraries', $contents))
            $userlibs = $contents['libraries'];

        $parsedLibs = $this->checkHeaders($files, $userlibs);

        $contents["libraries"] = $parsedLibs['libraries'];

        $request_content = json_encode($contents);

        // perform the actual post to the compiler
        $data = $apihandler->post_raw_data($this->container->getParameter('compiler'), $request_content);

        $decoded = json_decode($data, true);
        if ($decoded == null)
        {
            return new Response(json_encode(array("success" => false, "message"=> "Failed to get compiler response.")));
        }

        if ($decoded["success"] === false && !array_key_exists("step", $decoded))
            $decoded["step"] = "unknown";

        unset($parsedLibs['libraries']);
        $decoded['additionalCode'] = $parsedLibs;

        return new Response(json_encode($decoded));
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
            $foundPersonal = false;
            foreach ($personalLibs as $plibrary){
                foreach ($plibrary as $libcontent){
                    if ($libcontent["filename"] == $header.".h")
                        $foundPersonal = true;
                }
            }
            if ($foundPersonal === false) {

                $data = $apiHandler->get($libmanager_url . "/fetch?library=" . urlencode($header));
                $data = json_decode($data, true);

                if ($data["success"]) {
                    $libraries[$header] = $data["files"];
                    foreach ($data['files'] as $file) {
                        $foundFiles[] = $file['filename'];
                    }
                } elseif (!$data['success']){
                    $notFoundHeaders[] = $header . ".h";
                }
            }
        }
        return array('libraries' => $libraries, 'foundFiles' => $foundFiles, 'notFoundHeaders' => $notFoundHeaders);
    }
}

