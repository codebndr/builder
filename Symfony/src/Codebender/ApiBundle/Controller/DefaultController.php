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
     * @param array $files
     * @param array $userLibs
     *
     * @return array
     */
    protected function checkHeaders($files,  array $userlibs)
    {
        $apiHandler = $this->get('codebender_api.handler');

        $headers = $apiHandler->read_libraries($files);

        // declare arrays
        $libraries = $notFoundHeaders = $foundHeaders = $fetchedLibs = $providedLibs = array();

        $providedLibs = array_keys($userlibs);

        // get library manager url
        $libmanager_url = $this->container->getParameter('library');

        $libraries = $userlibs;

        foreach ($headers as $header) {

            $exists_in_request = false;
            foreach ($userlibs as $lib){
                foreach ($lib as $libcontent){
                    if ($libcontent["filename"] == $header.".h") {
                        $exists_in_request = true;
                        $foundHeaders[] = $header . ".h";
                    }
                }
            }
            if ($exists_in_request === false) {

                $data = $apiHandler->get($libmanager_url . "/fetch?library=" . urlencode($header));
                $data = json_decode($data, true);

                if ($data["success"]) {
                    $fetchedLibs[] = $header;
                    $files_to_add = array();
                    foreach ($data["files"] as $file){
                        if (in_array(pathinfo($file['filename'], PATHINFO_EXTENSION), array('cpp', 'h', 'c', 'S', 'inc')))
                            $files_to_add[] = $file;
                    }

                    $libraries[$header] = $files_to_add;
                    $foundHeaders[] = $header . ".h";

                } elseif (!$data['success']){
                    $notFoundHeaders[] = $header . ".h";
                }
            }
        }

        return array(
            'libraries' => $libraries,
            'providedLibraries' => $providedLibs,
            'fetchedLibraries' => $fetchedLibs,
            'detectedHeaders'=> $headers,
            'foundHeaders' => $foundHeaders,
            'notFoundHeaders' => $notFoundHeaders);
    }

    /**
      *
      * @param array $files
      * Checks if project id and user id txt files exist in the request files.
      * If not, creates these files with null id
     *
      */
    protected function checkForUserProject(&$files)
    {
        $foundProj = $foundUsr = false;

        foreach ($files as $file) {
            if (preg_match('/(?<=user_)[\d]+/', $file['filename'])) $foundUsr = true;
            if (preg_match('/(?<=project_)[\d]+/', $file['filename'])) $foundProj = true;
        }

        if (!$foundUsr) $files[] = array('filename' => 'user_null.txt', 'content' => '');
        if (!$foundProj) $files[] = array('filename' => 'project_null.txt', 'content' => '');
    }
}

