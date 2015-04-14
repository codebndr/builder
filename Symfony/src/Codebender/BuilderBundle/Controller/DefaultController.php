<?php

namespace Codebender\BuilderBundle\Controller;

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
     * @return Response Response instance.
     *
     */
    public function statusAction()
    {
        return new Response(json_encode(array("success" => true, "status" => "OK")));
    }

    /**
     * Gets a request for compilation or library fetching (depends on the 'type' field of the request)
     * and passes the request to either the compiler or the library manager.
     *
     * Includes several checks in order to ensure the validity of the data provided as well
     * as authentication.
     *
     * @param $auth_key
     * @param $version
     * @return Response
     */
    public function handleRequestAction($auth_key, $version)
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

        if ($contents === NULL)
        {
            return new Response(json_encode(array("success" => false, "message" => "Wrong data.")));
        }

        if (!array_key_exists("data", $contents))
        {
            return new Response(json_encode(array("success" => false, "message" => "Insufficient data provided.")));
        }

        if ($contents["type"] == "compiler") {
            return new Response($this->compile($contents["data"]));
        }

        if ($contents["type"] == "library")
        {
            return new Response($this->getLibraryInfo(json_encode($contents["data"])));
        }

        return new Response(json_encode(array("success" => false, "message" => "Invalid request type (can handle only 'compiler' or 'library' requests)")));
    }

    /**
     * Gets the data from the handleRequestAction and proceeds with the compilation
     *
     * @param $contents
     * @return Response
     */
    protected function compile($contents)
    {
        $apihandler = $this->get('codebender_builder.handler');

        $files = $contents["files"];

        $this->checkForUserProject($files);

        $userLibs = array();

        if (array_key_exists('libraries', $contents))
            $userLibs = $contents['libraries'];

        $parsedLibs = $this->checkHeaders($files, $userLibs);

        $contents["libraries"] = $parsedLibs['libraries'];

        $request_content = json_encode($contents);

        // perform the actual post to the compiler
        $data = $apihandler->post_raw_data($this->container->getParameter('compiler'), $request_content);

        $decoded = json_decode($data, true);
        if ($decoded === NULL)
        {
            return json_encode(array("success" => false, "message"=> "Failed to get compiler response."));
        }

        if ($decoded["success"] === false && !array_key_exists("step", $decoded))
        {
            $decoded["step"] = "unknown";
        }

        unset($parsedLibs['libraries']);
        $decoded['additionalCode'] = $parsedLibs;

        return json_encode($decoded);
    }

    /**
     * Gets a request for library information from the handleRequestAction and makes the
     * actual call to the library manager.
     * The data must be already json encoded before passing them to this function.
     *
     * @param $data
     * @return mixed
     */
    protected function getLibraryInfo($data)
    {
        $handler = $this->get('codebender_builder.handler');

        $libraryManager = $this->container->getParameter('library');

        return $handler->post_raw_data($libraryManager, $data);
    }

    /**
     *
     * @param array $files
     * @param array $userLibs
     *
     * @return array
     */
    protected function checkHeaders($files, $userLibs)
    {
        $apiHandler = $this->get('codebender_builder.handler');

        $headers = $apiHandler->read_libraries($files);

        // declare arrays
        $libraries = $notFoundHeaders = $foundHeaders = $fetchedLibs = $providedLibs = array();

        $providedLibs = array_keys($userLibs);

        $libraries = $userLibs;

        foreach ($headers as $header) {

            $exists_in_request = false;
            foreach ($userLibs as $lib){
                foreach ($lib as $libcontent){
                    if ($libcontent["filename"] == $header.".h") {
                        $exists_in_request = true;
                        $foundHeaders[] = $header . ".h";
                    }
                }
            }
            if ($exists_in_request === false) {

                $requestContent = array("type" => "fetch", "library" => $header);
                $data = $this->getLibraryInfo(json_encode($requestContent));
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

