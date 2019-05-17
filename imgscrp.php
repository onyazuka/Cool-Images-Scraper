<?php 
  if (!isset($_SERVER['argv'])) {
    die("To grab images, provided url in argv[1]!");
  }
  include_once "xml.php";
  include_once "lookup.php";

  // WARNING!!! Don't forget about COOKIES!


  /*
    Scraps images from url/website/internet.

    Arguments:
      argv[1] - options

    List of possible image scrapper options:
      (Already done):
      - Output dir(argv[2])
      - recursive [BOOL] - if not set, CONFLICTS with depth, whiteList, blackList, path
      - depth [NUMERIC] - CONFLICTS with Recursive(if depth is set and recursive is not) (STARTS from 1!!!, acts as max depth), path
      - whiteList [ARRAY of REGEX] - CONFLICTS WITH Black list(cannot exist both), path
      - blackList [ARRAY of REGEX] - CONFLICTS WITH White list(cannot exist both), path
      - path [ARRAY of REGEX] - array that is pattern of path, in which images can be parsed 
          conflicts with depth, whiteList, blackList
      - imageNamePatterns [ARRAY of REGEX]
      - maxImageSize [NUMERIC] - CONDITION maxImageSize >= minImageSize && maxImageSize >= 0
      - minImageSize [NUMERIC] - CONDITION maxImageSize >= minImageSize && minImageSize >= 0
      - fileRewrite [BOOL]
      (Not done):
    - createDir [ONE OF 'simple' OR 'recursive'] - if 'simple', tries to create directory only on one level, if 'recursive' - on multiple

  */
  class ImageScraper {

    private $args = array();
    private $url; 
    private $outputDir;
    private $options = array();
    private $lookup;
    private $curDepth = 0;
    
    public function __construct($_args) {
      $this->parseArgs($_args);
      $this->checkOptions();
      $this->lookup = LookupDict::get();
    }

    protected function checkIfUrlPasses($url) {

      // checking if visited
      if ($this->lookup->isUrlVisited($url)) return false;

      // checking depth
      if (isset($this->options['depth'])) {
        if ($this->curDepth > $this->options['depth']) return false;
      }

      // checking path
      if (isset($this->options['path'])) {
        // because curDepth starts from 1
        $level = $this->curDepth - 1;
        // path ended
        if ($level >= count($this->options['path'])) return false;
        if (!checkReWhite($url, $this->options['path'][$level])) return false;
      }

      // black list has biggest priority than white list
      $lists = ['blackList' => 'checkReBlackList', 'whiteList' => 'checkReWhiteList'];
      foreach ($lists as $list => $regexCheckFunc) {
        if (isset($this->options[$list])) {            
          if (!(is_array($this->options[$list]))) throw new Exception("ImageScrapper::checkIfUrlPasses() - 
                                        invalid $list argument, should be an array of regexps");
          if(!call_user_func_array($regexCheckFunc, array($url, $this->options[$list]))) return false;    
        }
      }
      return true;
    }

    protected function checkOptions() {
      $options = $this->options;
      $errPrefix = "ImageScrapper::checkOptions(): ";
    
      // mandatory options
      if (!isset($options['urls']) || !isset($options['outputDir'])) {
        die("$errPrefix - options 'urls' and 'outputDir' are mandatory!");
      }
      // option types
      checkIsSetAndHasType($options, 'urls', 'is_array');
      checkIsSetAndHasType($options, 'recursive', 'is_bool');
      checkIsSetAndHasType($options, 'depth', 'is_numeric');
      checkIsSetAndHasType($options, 'whiteList', 'is_array');
      checkIsSetAndHasType($options, 'blackList', 'is_array');
      checkIsSetAndHasType($options, 'path', 'is_array');
      checkIsSetAndHasType($options, 'imageNamePatterns', 'is_array');
      checkIsSetAndHasType($options, 'maxImageSize', 'is_numeric');
      checkIsSetAndHasType($options, 'maxImageSize', 'is_numeric');
      checkIsSetAndHasType($options, 'fileRewrite', 'is_bool');

      // recursive
      if (!array_key_exists("recursive", $options) && 
          (array_key_exists("depth", $options) || 
          array_key_exists("whiteList", $options) ||
          array_key_exists("blackList", $options) ||
          array_key_exists("path", $options))) {
        die("$errPrefix - conflict, 'recursive' is not set, and some of flags 'depth',
          'whiteList', 'blackList', 'path' is set, which makes no sense");
      }
      // white&black lists
      if (array_key_exists("whiteList", $options) && array_key_exists("blackList", $options)) {
        foreach ($options['whiteList'] as $whiteListItem) {
          foreach ($options['blackList'] as $blackListItem) {
            if ($whiteListItem === $blackListItem) {
              die("$errPrefix - conflict, value $whiteListItem is included both in white and black lists");
            }
          } 
        }
      }
      if (array_key_exists("path", $options) && (
          array_key_exists("depth", $options) ||
          array_key_exists("whiteList", $options) ||
          array_key_exists("blackList", $options) 
        ))
      {
        die("$errPrefix - path options conflicts with depth, whiteList and blackList options!");
      }

      foreach (["minImageSize", "maxImageSize"] as $option) {
        if (array_key_exists($option, $options) && $options[$option] < 0) {
          die("$errPrefix - minImageSize and maxImageSize can not be negative!");
        }
      }

      if (array_key_exists("minImageSize", $options) && array_key_exists("maxImageSize", $options)) {
        if ($options["minImageSize"] > $options["maxImageSize"]) {
          die("$errPrefix - logic error, minImageSize can not be bigger, than maxImagesize");
        }
      }
    }


    private function initStage() {
      $this->curDepth = 0;
      $this->lookup->clear();
    }

    /*
      Returns $args object
    */
    protected function parseArgs($argv) {
      if(count($argv) !== 2) {
        die("Invalid args\nUsage: script.php options_file");
      } 
      if (isset($argv[1])) $this->options = readFileGetJson($argv[1]);
      $this->urls = $this->options['urls'];
      $this->outputDir = $this->options['outputDir'];
    }

    /*
      Returns true if $img is an image
    */
    protected function tryProcessImage($imgUrl) {
      list($header, $headerInfo) = load_with_curl($imgUrl, "HEAD");
      if (preg_match("/image.*/" , $headerInfo['content_type']))
      {
        $this->saveImage($imgUrl, $headerInfo);
        return true;
      } else {
        return false;
      }
    }

    /*
      Should be always called instead of direct 'processPage' call.
      Manages curDepth, acts as wrapper.
    */
    protected function processPageWrapper($url) {
      $this->curDepth += 1;
      $this->processPage($url);
      $this->curDepth -= 1;
    }

    protected function processPage($url) {    
      if (!$this->checkIfUrlPasses($url)) {
        $this->lookup->setUrlVisited($url);
        return;
      };
      $this->lookup->setUrlVisited($url);

      $outputDir = $this->outputDir;
      $options = $this->options;
      print "Visiting: " . $url . "\n";

      if ($this->tryProcessImage($url)) {
        return;
      }
      else {
        // if last depth level and NOT image, have nothing to do here mode
        if(isset($this->options['depth']) && $this->curDepth === $this->options['depth']) {
          return;
        }
      }

      // normalizing output file name so it ends with '/'
      $outputDir = $outputDir[strlen($outputDir) - 1] === '/' ? $outputDir : $outputDir . '/';
      
      list($header, $headerInfo) = load_with_curl($url, "HEAD");
      $headerArr = httpHeaderToArray($header);

      list($page, $pageInfo) = load_with_curl($url);
      // content can be compressed
      if(isset($headerArr['Content-Encoding']) && 
          in_array($headerArr['Content-Encoding'], ['gzip', 'deflate']))
      {
        $page = zlib_decode($page);
      }
      
      
      if(!strlen($page)) {
        print ("Invalid page by URL " . $url);
        return;
      }

      // doing base url
      $urlParts = parse_url($pageInfo['url']);
      $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'];

      try {
        $xml = new XMLLogic($page);
      } catch(Exception $e) { 
        print "Warning: $url gives incorrect XML - cannot parse!";
        return;
      }
      $imgs = $xml->xPathRequest('//xhtml:img/@src');
      $links = $xml->xPathRequest('//xhtml:a/@href');

      foreach ($imgs as $img) {
        $this->tryProcessImage($img->nodeValue);
      }
      

      // recursive
      if ($this->options['recursive']) {
        foreach ($links as $link) {
          $newUrl = $link->nodeValue;
          if(isUrlRelative($newUrl)) {
            // normalizing relative url
            if($newUrl[0] === '/') {
              $newUrl = substr($newUrl, 1);
            }
            $newUrl = $baseUrl . '/' . $newUrl;
          }
          
          $this->processPageWrapper($newUrl);
        }
      }
    }

    public function run() {
      foreach ($this->urls as $url) {
        $this->initStage();
        $this->processPageWrapper($url);
      }
      print "Finished\n";
    }


    protected function saveImage($url, $urlInfo) {
      // checking min and max sizes
      if(isset($this->options['minImageSize']) && 
          $urlInfo['download_content_length'] < $this->options['minImageSize']) {
        return;
      }
      if(isset($this->options['maxImageSize']) && 
          $urlInfo['download_content_length'] > $this->options['maxImageSize']) {
        return;
      }

      $patternOk = false;

      $filename = getFileNameByUrl($url);
      if (isset($this->options['imageNamePatterns'])) {
        foreach ($this->options['imageNamePatterns'] as $namePattern) {
          if (preg_match($namePattern, $filename)) $patternOk = true;
        }
      }

      if (!isset($this->options['imageNamePatterns']) || $patternOk) {
        $fullPathName = $this->outputDir . $filename;
        // if image file exists - doing nothing
        if($this->lookup->isImageExists($filename)) return;
        if(file_exists($fullPathName)) {
          // fileRewrite options is not set
          if(!(isset($this->options['fileRewrite']) && 
                      $this->options['fileRewrite'] === true)) {
            print "Skipping " . $fullPathName . " already exists, and option fileRewrite is not set\n";
            return;
          }
        }
        list($file, $respInfo) = load_with_curl($url);
        // write error
        if (!file_put_contents($fullPathName, $file)) {
          print "WARNING: Cannot write in file " . $fullPathName;
        }
        // write ok
        else {
          print "Written in " . $fullPathName . "\n";
          $this->lookup->setImgExists($filename);
        }
        // no need to check other patterns
        return;
      }
    }
  }

  function checkIsSetAndHasType($context, $val, $checkFunc) {
    if(isset($context[$val]) && !$checkFunc($context[$val])) {
      die("checkIsSetAndHasType() - invalid $val argument type, condition $checkFunc is not ok");
    } 
  }

  // if url matches to at least on re from blacklist, returns false
  function checkReBlackList($url, $blackList) {
    foreach($blackList as $blackre) {
      if (preg_match($blackre, $url)) return false;
    }
    return true;
  }
  
  // if url not matches to any re from white list, returns false
  function checkReWhiteList($url, $whitelist) {
    foreach($whitelist as $whitere) {
      if (checkReWhite($url, $whitere)) return true;
    }
    return false;
  } 

  function checkReWhite($url, $whitere) {
    if (preg_match($whitere, $url)) return true;
  }


  function getFileNameByUrl($url) {
    return array_slice(explode('/', $url), -1)[0];
  }


  function isUrlRelative($url) {
    return !preg_match('#^\w+://#', $url);
  }

  function httpHeaderToArray($header) {
    $strings = explode("\n", $header);
    $res = array();
    foreach ($strings as $str) {
      $keyval = explode(':', $str);
      if(count($keyval) !== 2) continue;
      $res[trim($keyval[0])] = trim($keyval[1]);
    }
    return $res;
  }


  function load_with_curl($url, $method="GET") {
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


  function readFileGetJson($fname) {
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


  $imgscrp = new ImageScraper($_SERVER['argv']);
  $imgscrp->run();

?>