<?php

namespace Codebender\ApiBundle\Handler;

class DefaultHandler
{
    public function get_data($url, $var, $value)
    {
        $curlHandle = curl_init();
        $timeout = 10;
        curl_setopt($curlHandle,CURLOPT_URL,$url);
        curl_setopt($curlHandle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curlHandle,CURLOPT_CONNECTTIMEOUT,$timeout);

        curl_setopt($curlHandle,CURLOPT_POST,1);
        curl_setopt($curlHandle,CURLOPT_POSTFIELDS,$var.'='.$value);

        $data = curl_exec($curlHandle);
        curl_close($curlHandle);
        return $data;
    }

    public function post_raw_data($url, $raw_data)
    {
        $curlHandle = curl_init();
        $timeout = 10;
        curl_setopt($curlHandle, CURLOPT_URL,$url);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $timeout);

        curl_setopt($curlHandle, CURLOPT_POST, 1);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $raw_data);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($curlHandle);

        curl_close($curlHandle);
        return $data;
    }

    public function get($url)
    {
        $curlHandle = curl_init();
        $timeout = 10;
        curl_setopt($curlHandle,CURLOPT_URL,$url);
        curl_setopt($curlHandle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curlHandle,CURLOPT_CONNECTTIMEOUT,$timeout);

        $data = curl_exec($curlHandle);
        curl_close($curlHandle);
        return $data;
    }

      /**
          \brief Extracts included headers from source code.

          \param string $code The program's source code.
          \return An array of headers.

          Takes a string containing the source code of a C/C++ program, parses the
          preprocessor directives and makes a list of header files to include. The
          postfix <b>.h</b> is removed from the header names.
       */
      function read_headers($code)
      {
          // Matches preprocessor include directives, has high tolerance to
          // spaces. The actual header (without the postfix .h) is stored in
          // register 1.
          //
          // Examples:
          // #include<stdio.h>
          // # include "proto.h"
          $REGEX_ARROWS = "/^\s*#\s*include\s*<\s*([a-zA-Z0-9_+]*)\.h\s*>/";
          $REGEX_QUOTES = "/^\s*#\s*include\s*\"\s*([a-zA-Z0-9_+]*)\.h\s*\"/";

          $headers = array("arrows" => array(), "quotes" => array());
          foreach (explode("\n", $code) as $line)
          {
              if (preg_match($REGEX_ARROWS, $line, $matches))
                  $headers["arrows"][] = $matches[1];
              if (preg_match($REGEX_QUOTES, $line, $matches))
                  $headers["quotes"][] = $matches[1];
          }

          $headers["arrows"] = array_unique($headers["arrows"]);
          $headers["quotes"] = array_unique($headers["quotes"]);

          return $headers;
      }

    function read_libraries($sketch_files)
    {
        // Scan files for headers and locate the corresponding include paths.
        $headers = array("arrows" => array(), "quotes" => array());

        foreach ($sketch_files as $file)
        {
            if (strrpos($file["filename"], ".ino") == strlen($file["filename"]) - 4 &&
                strrpos($file["filename"], ".ino") !== false)
            {
                $code = $file["content"];
                $headers = $this->read_headers($code);

                foreach ($headers["quotes"] as $key => $header)
                {
                    foreach ($sketch_files as $file)
                    {
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
