<?php 

    function checkIsSetAndHasType(array $context, string $val, string $checkFunc) {
      if(isset($context[$val]) && !$checkFunc($context[$val])) {
        die("checkIsSetAndHasType() - invalid $val argument type, condition $checkFunc is not ok");
      } 
    }

    // if url matches to at least on re from blacklist, returns false
    function checkReBlackList(string $url, array $blackList) {
      foreach($blackList as $blackre) {
        if (preg_match($blackre, $url)) return false;
      }
      return true;
    }
    
    // if url not matches to any re from white list, returns false
    function checkReWhiteList(string $url, array $whitelist) {
      foreach($whitelist as $whitere) {
        if (checkReWhite($url, $whitere)) return true;
      }
      return false;
    } 
  
    function checkReWhite(string $url, $whitere) {
      if (preg_match($whitere, $url)) return true;
    }
  
  
    function getFileNameByUrl(string $url) {
      return array_slice(explode('/', $url), -1)[0];
    }
  
  
    function isUrlRelative(string $url) {
      return !preg_match('#^\w+://#', $url);
    }
  
    function httpHeaderToArray(string $header) {
      $strings = explode("\n", $header);
      $res = array();
      foreach ($strings as $str) {
        $keyval = explode(':', $str);
        if(count($keyval) !== 2) continue;
        $res[trim($keyval[0])] = trim($keyval[1]);
      }
      return $res;
    }
  
  
    function load_with_curl(string $url, string $method="GET") {
      $c = curl_init($url);
  
      $customHeaders = [];
      $customHeaders[] = "Accept-Encoding: identity";
  
      curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($c, CURLOPT_HTTPHEADER, $customHeaders);
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
  
  
    function readFileGetJson(string $fname) {
      if(!file_exists($fname)) {
        throw new Exception("readFileGetJson() - file $fname not exists");
      }
      if(($fileContents = file_get_contents($fname)) === false) {
        throw new Exception("readFileGetJson() - cannot read file $fname");
      }
      if (!$fileJson = json_decode($fileContents, true)) {
        throw new Exception("readFileGetJson() - file $fname has invalid json format.");
      } 
      return $fileJson;
    }

?>