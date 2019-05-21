<?php

  /*
    Main purpose of this is to make "Connection: Keep-Alive" work(with preserving curl descriptor)
  */
  class CurlFacade {

    public function __construct() {
      $this->curlDesc = curl_init();
    }

    public function __destruct() {
      curl_close($this->curlDesc);
    }

    public function makeRequest(array $options) {
      $c = $this->curlDesc;
      // resetting previous options
      curl_reset($c);
      $url = $options['url'];
      $method = $options['method'];
      $cookies = $options['cookies'];
      $cookieJar = $options['cookieJar'];
      $headers = $options['headers'];
      if(!isset($url) || !isset($method)) throw new Error("CurlAdapter::makeRequest() - url and method must be set");
  
      curl_setopt($c, CURLOPT_URL, $url);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
      if (isset($cookies)) {
        if (!curl_setopt($c, CURLOPT_COOKIE, $cookies)) {
          print "WARNING: curl could not set provided cookies";
        }
      } 
      if (isset($cookieJar)) {
        if (!curl_setopt($c, CURLOPT_COOKIEJAR, $cookieJar)) {
          print "WARNING: curl could not set provided cookieJar";
        }
      }
      if (isset($headers)) {
        if (!curl_setopt($c, CURLOPT_HTTPHEADER, $headers)) {
          print "WARNING: curl could not set provided headers";
        }
      }
      if ($method == 'GET') {
        // follow redirects
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
      }
      else if ($method == 'HEAD') {
        // not include body
        curl_setopt($c, CURLOPT_NOBODY, true);
        //include header
        curl_setopt($c, CURLOPT_HEADER, true);
      }
      $response = curl_exec($c);
      return array($response, curl_getinfo($c));
    }

  }

?>