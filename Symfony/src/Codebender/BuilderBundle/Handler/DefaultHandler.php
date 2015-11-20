<?php

namespace Codebender\BuilderBundle\Handler;

class DefaultHandler
{

    /**
     * Uses PHP's curl in order to perform a POST request
     * to the given URL with the provided data.
     *
     * @param $url
     * @param $rawData
     * @return mixed
     */
    public function postRawData($url, $rawData) {
        $curlHandle = curl_init();
        $timeout = 10;
        curl_setopt($curlHandle, CURLOPT_URL,$url);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $timeout);

        curl_setopt($curlHandle, CURLOPT_POST, 1);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $rawData);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($curlHandle);

        curl_close($curlHandle);
        return $data;
    }

    /**
     * Extracts included headers from source code.
     * Takes a string containing the source code of a C/C++ program, parses the
     * preprocessor directives and makes a list of header files to include. The
     * postfix <b>.h</b> is removed from the header names.
     *
     * @param array $code The program's source code
     * @return array An array of headers
     */
    function detectHeadersInFile($code) {
        /*
         * Matches preprocessor include directives, has high tolerance to
         * spaces. The actual header (without the postfix .h) is stored in
         * register 1.
         *
         * Examples:
         * #include<stdio.h>
         * # include "proto.h"
         *
         */
        $arrowsRegex = "/^\s*#\s*include\s*<\s*([a-zA-Z0-9_+]*)\.h\s*>/";
        $quotesRegex = "/^\s*#\s*include\s*\"\s*([a-zA-Z0-9_+]*)\.h\s*\"/";

        $headers = ["arrows" => [], "quotes" => []];
        foreach (explode("\n", $code) as $line)
        {
          if (preg_match($arrowsRegex, $line, $matches))
              $headers["arrows"][] = $matches[1];
          if (preg_match($quotesRegex, $line, $matches))
              $headers["quotes"][] = $matches[1];
        }

        $headers["arrows"] = array_unique($headers["arrows"]);
        $headers["quotes"] = array_unique($headers["quotes"]);

        return $headers;
    }

    /**
     * Detects the .ino file in the project files, then calls
     * detectHeadersInFile and returns the headers detected in the
     * Arduino code.
     *
     * @param $sketchFiles
     * @return array
     */
    function readLibraries($sketchFiles) {
        // Scan files for headers and locate the corresponding include paths.
        $headers = ["arrows" => [], "quotes" => []];

        foreach ($sketchFiles as $file)
        {
            if (pathinfo($file['filename'], PATHINFO_EXTENSION) === 'ino') {
                $code = $file["content"];
                $headers = $this->detectHeadersInFile($code);

                foreach ($headers["quotes"] as $key => $header) {
                    foreach ($sketchFiles as $file) {
                        if ($file["filename"] == $header.".h")
                            unset($headers["quotes"][$key]);
                    }
                }
                break;
            }
        }

        $libraries = array_unique(array_merge($headers["arrows"], $headers["quotes"]));
        return $libraries;
    }
}
