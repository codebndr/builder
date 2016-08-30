<?php

namespace Codebender\BuilderBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * default controller of api bundle
 */
class DefaultController extends Controller
{
    /* @var array $additionalCode */
    protected $additionalCode = [];

    /**
     * status action
     *
     * @return JsonResponse
     *
     */
    public function statusAction()
    {
        return new JsonResponse(['success' => true, 'status' => 'OK']);
    }

    /**
     * Gets a request for compilation or library fetching (depends on the 'type' field of the request)
     * and passes the request to either the compiler or the library manager.
     *
     * @return JsonResponse
     */
    public function handleRequestAction()
    {
        $contents = json_decode($this->getRequest()->getContent(), true);

        $isContentValid = $this->isContentValid($contents);
        if ($isContentValid['success'] !== true) {
            return new JsonResponse(['success' => false, 'message' => $isContentValid['error']]);
        }

        if ($contents['type'] == 'compiler') {
            return new JsonResponse($this->compile($contents['data']));
        }

        if ($contents['type'] == 'library') {
            return new JsonResponse($this->getLibraryInfo(json_encode($contents['data'])));
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Invalid request type (can handle only \'compiler\' or \'library\' requests)'
        ]);
    }

    protected function isContentValid($requestContent)
    {
        if (!array_key_exists('data', $requestContent)) {
            return ['success' => false, 'error' => 'Insufficient data provided.'];
        }

        return ['success' => true];
    }

    /**
     * Gets the data from the handleRequestAction and proceeds with the compilation
     *
     * @param $contents
     * @return array
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    protected function compile($contents)
    {
        $apiHandler = $this->get('codebender_builder.handler');

        $contents = $this->generateCompilerPayload($contents);

        $compilerRequestContent = json_encode($contents);

        // perform the actual request to the compiler
        $data = $apiHandler->postRawData($this->container->getParameter('compiler'), $compilerRequestContent);

        $decodedResponse = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Failed to get compiler response.'];
        }

        if ($decodedResponse['success'] === false && !array_key_exists('step', $decodedResponse)) {
            $decodedResponse['step'] = 'unknown';
        }

        $decodedResponse['additionalCode'] = $this->additionalCode;
        $this->additionalCode = [];

        return $decodedResponse;
    }

    /**
     * Gets a request for library information from the handleRequestAction and makes the
     * actual call to the library manager.
     * The data must be already json encoded before passing them to this function.
     *
     * @param $data
     * @return array
     */
    protected function getLibraryInfo($data)
    {
        $handler = $this->get('codebender_builder.handler');

        $libraryManager = $this->container->getParameter('library_manager');

        $response = json_decode($handler->postRawData($libraryManager, $data), true);

        if (json_last_error() != JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Cannot fetch library data'];
        }

        return $response;
    }

    /**
     *
     * @param array $projectFiles
     * @param array $userLibraries
     *
     * @return array
     */
    protected function returnProvidedAndFetchedLibraries($projectFiles, $userLibraries)
    {
        $apiHandler = $this->get('codebender_builder.handler');

        $detectedHeaderstemp = $apiHandler->readLibraries($projectFiles);

        // declare arrays
        $notFoundHeaders = [];
        $foundHeaders = [];
        $librariesFromLibman = [];
        $providedLibraries = array_keys($userLibraries);
        $libraries = $userLibraries;

        foreach ($detectedHeaderstemp as $header) {

            $existsInRequest = false;
            // TODO We can do this in a better way
            foreach ($userLibraries as $library) {
                foreach ($library as $libraryContent) {
                    if ($libraryContent["filename"] == $header) {
                        $existsInRequest = true;
                        $foundHeaders[] = $header;
                    }
                }
            }

            if ($existsInRequest === true) {
                continue;
            }

            //Fetch lib from library manager by name
		$headername = pathinfo($header, PATHINFO_FILENAME);

            $requestContent = ["type" => "fetch", "library" => $headername];
            $data = $this->getLibraryInfo(json_encode($requestContent));

            if ($data['success'] === false) {
                $notFoundHeaders[] = $header;
                continue;
            }

            $foundHeaders[] = $header;
            $librariesFromLibman[] = $header;
            $filesToBeAdded = [];
            foreach ($data["files"] as $file) {
                if (in_array(pathinfo($file['filename'], PATHINFO_EXTENSION), array('cpp', 'h', 'hpp', 'c', 'S', 'inc')))
                    $filesToBeAdded[] = $file;
            }
            $libraries[$header] = $filesToBeAdded;
        }

        /*
         * Get only the names of the header files.
         * Until we make codebender handles headers along with their extensions (e.g. "Ethernet.h", not "Ethernet"),
         * this must stay here for compatibility reasons.
         */
	 foreach ($detectedHeaderstemp as $headerfiles) {
	     $detectedHeaders[] = pathinfo($headerfiles, PATHINFO_FILENAME);
            }


        // store info about libraries and headers in the `additionalCode` class property;
        $this->additionalCode = [
            'providedLibraries' => $providedLibraries,
            'fetchedLibraries' => $librariesFromLibman,
            'detectedHeaders'=> $detectedHeaders,
            'foundHeaders' => $foundHeaders,
            'notFoundHeaders' => $notFoundHeaders
        ];

        return $libraries;
    }

    /**
     * Returns the payload used for compilation. Parses projects files and libraries
     * already existing in request and fetches any necessary libraries from eratosthenes.
     *
     * @return JsonResponse
     */
    public function generatePayloadAction()
    {
        $providedPayload = json_decode($this->getRequest()->getContent(), true);

        $payload = $this->generateCompilerPayload($providedPayload);
        if (empty($payload)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid compilation payload provided.']);
        }

        $payload['success'] = true;
        $payload['additionalCode'] = $this->additionalCode;
        $this->additionalCode = [];

        return new JsonResponse($payload);
    }

    /**
     * Returns the payload of a compilation request. Sketch files and board-related
     * data (build, core, variant) must be provided in the initial payload.
     *
     * @param array $providedData
     * @return array
     */
    protected function generateCompilerPayload(array $providedData)
    {
        $payload = $this->addUserIdProjectIdIfNotInRequest($providedData);

        if (!array_key_exists('files', $payload)) {
            return [];
        }

        $files = $payload['files'];

        $userLibraries = [];

        if (array_key_exists('libraries', $payload)) {
            $userLibraries = $payload['libraries'];
        }

        $payload['libraries'] = $this->returnProvidedAndFetchedLibraries($files, $userLibraries);

        return $payload;
    }

    /**
      * Checks if project id and user id exist in the request.
      * If not, adds the fields with null id
      *
      * @param array $requestContents
      * @return array
      */
    protected function addUserIdProjectIdIfNotInRequest($requestContents)
    {
        $nullDefaults = ['userId' => 'null', 'projectId' => 'null'];
        return array_merge($nullDefaults, (array)$requestContents);
    }
}

