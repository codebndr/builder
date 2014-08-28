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
    public function compilelibrariesAction()
    {
        $request = $this->getRequest()->getContent();
        $requestArr = json_decode($request, true);

        $headers = $requestArr['nonPersonalLib'];
        $matchedPersonalLib = $requestArr['matchedPersonallibrary'];
        $contents = $requestArr['contents'];

        $apihandler = $this->get('codebender_api.handler');

        // get library manager url.
        $libmanager_url = $this->container->getParameter('library');

        $foundFiles = $notFoundHeaders = array();
        $libraries = $matchedPersonalLib;
        if (!empty($headers)) {
            foreach ($headers as $header) {

                $data = $apihandler->get($libmanager_url . "/fetch?library=" . urlencode($header));
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

        $lib_resp_time = microtime(true);
        $contents["libraries"] = $libraries;
        $request_content = json_encode($contents);
        $comp_req_time = microtime(true);

        // compile into compiler with passing request_contents data.
        $data = $apihandler->post_raw_data($this->container->getParameter('compiler'), $request_content);

        $responsedata = array('library' => $libraries, 'foundFiles' => $foundFiles, 'notFoundHeaders' => $notFoundHeaders, 'compileResponse' => $data);

        return new Response(json_encode($responsedata), 200);
    }

}

