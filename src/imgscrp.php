<?php 

  if (!isset($_SERVER['argv'])) {
    die("To grab images, provided url in argv[1]!");
  }
  include_once "xml.php";
  include_once "lookup.php";
  include_once "utils.php";
  include_once "curlfacade.php";
  

  // WARNING!!! Don't forget about COOKIES!


  /*
    Scraps images from url/website/internet.

    Arguments:
      args [STRING - options.json OR ARRAY of options] - options

    List of possible image scraper options:
      (Already done):
      - outputDir - [STRING or ARRAY] - if string, all filed will be saved in this dir, if array, it must have a folder for each url
          CONDITION: if outputDir is ARRAY, size(outputDir) == size(urls)
      - recursive [BOOL] - if not set, CONFLICTS with depth, whiteList, blackList, path
      - depth [NUMERIC] - CONFLICTS with Recursive(if depth is set and recursive is not) (STARTS from 1!!!, acts as max depth), path
      - whiteList [ARRAY of REGEX] - CONFLICTS WITH Black list(cannot exist same values in both), path
      - blackList [ARRAY of REGEX] - CONFLICTS WITH White list(cannot exist same values in both), path
      - path [ARRAY of REGEX] - array that is pattern of path, in which images can be parsed 
          conflicts with depth, whiteList, blackList
      - imageNamePatterns [ARRAY of REGEX]
      - maxImageSize [NUMERIC] - CONDITION maxImageSize >= minImageSize && maxImageSize >= 0
      - minImageSize [NUMERIC] - CONDITION maxImageSize >= minImageSize && minImageSize >= 0
      - fileRewrite [BOOL] - default behaviour is skip
      - createDirIfNotExists [STRING] - 'simple' or 'recursive'
      - initCookies [ARRAY or STRING in http cookie format]
      - cookieJar [STRING] - file to store cookies(WARNING: cookies are saved only after curl closes descriptor)
      - additionalHeaders [ARRAY] - additional HTTP HEADERS in format array('Content-type: text/plain', 'Content-length: 100') 
  */
  class ImageScraper {


    private $args = array();
    private $urls; 
    private $outputDirs;
    private $options = array();
    private $lookup;
    private $curDepth = 0;
    private $curl;

    private $curUrl;
    private $curOutputDir;
    
    public function __construct($_args) {
      $this->parseArgs($_args);
      $this->checkOptions();
      $this->lookup = LookupDict::get();
      $this->curl = new CurlFacade();
    }

    // url and outputDirs must be saved
    public function clearOptions() {
      $newOptions = [];
      $newOptions['urls'] = $this->options['urls'];
      $newOptions['outputDirs'] = $this->options['outputDirs'];
      $this->options = $newOptions;
      
    }

    public function getOption(string $key) {
      return $this->options[$key];
    }

    public function run() {
      foreach ($this->urls as $i => $url) {
        $this->initStage($i);
        $this->processPageWrapper($url);
      }
      print "Finished\n";
    }

    public function setOption(string $key, $val) {
      // memoizing old value in case of exception
      $oldVal = isset($this->options[$key]) ? $this->options[$key] : null;
      $this->options[$key] = $val;
      try {
        $this->checkOptions();
        $this->setMandatoryOptions();
      }
      catch(Exception $e) {
        // restoring old value
        if($oldVal !== null) $this->options[$key] = $oldVal;
        throw $e;
      }
      return $this;
    }

    protected function checkIfImageOkToSave(string $filename, array $urlInfo) {
      // if image file exists - doing nothing
      if($this->lookup->isImageExists($filename)) return false;
      
      // checking min and max sizes
      if(isset($this->options['minImageSize']) && 
          $urlInfo['download_content_length'] < $this->options['minImageSize']) {
        return false;
      }
      if(isset($this->options['maxImageSize']) && 
          $urlInfo['download_content_length'] > $this->options['maxImageSize']) {
        return false;
      }

      if (isset($this->options['imageNamePatterns'])) {
        foreach ($this->options['imageNamePatterns'] as $namePattern) {
          if (preg_match($namePattern, $filename)) return true;
        }
        return false;
      }
      else return true;
    }

    protected function checkIfUrlPasses(string $url) {

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

      $this->checkMandatoryOptions();
      
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
      checkIsSetAndHasType($options, "createDirIfNotExists", "is_string");
      checkIsSetAndHasType($options, "initCookies", "is_string");
      checkIsSetAndHasType($options, "cookieJar", "is_string");
      checkIsSetAndHasType($options, "additionalHeaders", "is_array");

      // recursive
      if (!array_key_exists("recursive", $options) && 
          (array_key_exists("depth", $options) || 
          array_key_exists("whiteList", $options) ||
          array_key_exists("blackList", $options) ||
          array_key_exists("path", $options))) {
        throw new Exception("$errPrefix - conflict, 'recursive' is not set, and some of flags 'depth',
          'whiteList', 'blackList', 'path' is set, which makes no sense");
      }

      // white&black lists
      if (array_key_exists("whiteList", $options) && array_key_exists("blackList", $options)) {
        foreach ($options['whiteList'] as $whiteListItem) {
          foreach ($options['blackList'] as $blackListItem) {
            if ($whiteListItem === $blackListItem) {
              throw new Exception("$errPrefix - conflict, value $whiteListItem is included both in white and black lists");
            }
          } 
        }
      }

      // path
      if (array_key_exists("path", $options) && (
          array_key_exists("depth", $options) ||
          array_key_exists("whiteList", $options) ||
          array_key_exists("blackList", $options) 
        ))
      {
        throw new Exception("$errPrefix - path options conflicts with depth, whiteList and blackList options!");
      }

      // minImageSize, maxImageSize
      foreach (["minImageSize", "maxImageSize"] as $option) {
        if (array_key_exists($option, $options) && $options[$option] < 0) {
          throw new Exception("$errPrefix - minImageSize and maxImageSize can not be negative!");
        }
      }

      if (array_key_exists("minImageSize", $options) && array_key_exists("maxImageSize", $options)) {
        if ($options["minImageSize"] > $options["maxImageSize"]) {
          throw new Exception("$errPrefix - logic error, minImageSize can not be bigger, than maxImagesize");
        }
      }

      // createDirIfNotExists
      if (isset($this->options['createDirIfNotExists'])) {
        if (!in_array($this->options['createDirIfNotExists'], ["simple", "recursive"])) {
          throw new Exception("$errPrefix - createDirIfNotExists must be one of 'simple' or 'recursive'");
        }
      }

      // cookieJar file must exist or be creatable
      if (isset($this->options['cookieJar'])) {
        if (!file_exists($this->options['cookieJar'])) {
          if (!touch($this->options['cookieJar'])) {
            throw new Exception("$errPrefix - 'cookieJar' file must already exist or be creatable");
          }
        }
      }
    }

    protected function checkMandatoryOptions() {
      // mandatory options
      if (!isset($this->options['urls']) || !isset($this->options['outputDirs'])) {
        throw new Exception("ImageScraper::parseArgs() - options 'urls' and 'outputDirs' are mandatory!");
      }
    }

    /*
      Made separate method for it for testing purposes.
    */
    protected function makeRequest($url, $method="GET") {
      $options = [];
      $options['url'] = $url;
      $options['method'] = $method;
      $options['cookies'] = isset($this->options['initCookies']) ? 
                            $this->options['initCookies'] : null;
      $options['cookieJar'] = isset($this->options['cookieJar']) ?
                            $this->options['cookieJar'] : null;
      $options['headers'] = isset($this->options['additionalHeaders']) ?
                            $this->options['additionalHeaders'] : null;
      return $this->curl->makeRequest($options);
    }


    private function initStage(int $i) {
      $this->curUrl = $this->urls[$i];
      $this->curOutputDir = is_array($this->outputDirs) ? 
                            $this->outputDirs[$i] : 
                            $this->outputDirs;
      if (isset($this->options['createDirIfNotExists']) && !is_dir($this->curOutputDir)) {
        $recursive = $this->options['createDirIfNotExists'] === "recursive" ? true : false;
        mkdir($this->curOutputDir, 0777, $recursive);
      }
      $this->curDepth = 0;
      $this->lookup->clear();
    }

    /*
      Returns $args object
    */
    protected function parseArgs($args) {
      if(is_string($args)) $this->options = readFileGetJson($args);
      else if(is_array($args)) $this->options = $args;
      else throw new Exception("ImageScraper::parseArgs() - invalid 'args' type, must be array of options or string to .json configuration file");
      // mandatory options
      $this->checkMandatoryOptions();
      $this->setMandatoryOptions();
      
      // normalizing createDirIfNotExists
      if (isset($this->options['createDirIfNotExists'])) {
        $this->options['createDirIfNotExists'] = strtolower($this->options['createDirIfNotExists']);
      }
      // normalizing initCookies
      if (isset($this->options['initCookies'])) {
        if (is_array($this->options['initCookies'])) {
          $this->options['initCookies'] = cookiesArrayToHttpCookies($this->options['initCookies']);
        }
      }
    }


    /*
      Should be always called instead of direct 'processPage' call.
      Manages curDepth, acts as wrapper.
    */
    protected function processPageWrapper(string $url) {
      $this->curDepth += 1;
      $this->processPage($url);
      $this->curDepth -= 1;
    }

    protected function processPage(string $url) {    
      if (!$this->checkIfUrlPasses($url)) {
        // cannot do it before, because checKIfUrlPasses also checks if page is visited
        $this->lookup->setUrlVisited($url);
        return;
      };
      $this->lookup->setUrlVisited($url);

      $outputDir = $this->curOutputDir;
      $options = $this->options;
      print "Visiting: " . $url . "\n";

      if ($this->tryProcessImage($url)) {
        // processed
        return;
      }

      // normalizing output file name so it ends with '/'
      $outputDir = $outputDir[strlen($outputDir) - 1] === '/' ? $outputDir : $outputDir . '/';
      
      list($header, $headerInfo) = $this->makeRequest($url, "HEAD");
      $headerArr = httpHeaderToArray($header);

      list($page, $pageInfo) = $this->makeRequest($url);
      // content can be compressed
      if(isset($headerArr['Content-Encoding']) && 
          in_array($headerArr['Content-Encoding'], ['gzip', 'deflate']))
      {
        $page = zlib_decode($page);
      }
      
      if(!strlen($page) || !isset($pageInfo['url'])) {
        print ("Invalid page by URL " . $url . "\n");
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

      // if last depth level and NOT image, have nothing to do here anymore
      if(isset($this->options['depth']) && 
        $this->curDepth === $this->options['depth'] &&
        !preg_match("/image.*/" , $headerInfo['content_type'])
        ) {
        return;
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

    protected function saveImage(string $url, array $urlInfo) {
      $filename = getFileNameByUrl($url);
      $fullPathName = $this->curOutputDir . $filename;
      
      if ($this->checkIfImageOkToSave($filename, $urlInfo)) {
        if(file_exists($fullPathName)) {
          // fileRewrite options is not set
          if(!(isset($this->options['fileRewrite']) && 
                      $this->options['fileRewrite'] === true)) {
            print "Skipping " . $fullPathName . " already exists, and option fileRewrite is not set\n";
            return;
          }
        }
        list($file, $respInfo) = $this->makeRequest($url);
        // write error
        if (!$this->writeFile($fullPathName, $file)) {
          throw new Exception("ImageScraper::saveImage() Cannot write in file " . $fullPathName);
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

    protected function setMandatoryOptions() {
      $this->urls = $this->options['urls'];
      $this->outputDirs = $this->options['outputDirs'];
    }

    /*
      Returns true if $img is an image
    */
    protected function tryProcessImage(string $imgUrl) {
      list($header, $headerInfo) = $this->makeRequest($imgUrl, "HEAD");
      if(!isset($headerInfo['content_type'])) return false;
      if (preg_match("/image.*/" , $headerInfo['content_type']))
      {
        $this->saveImage($imgUrl, $headerInfo);
        return true;
      } else {
        return false;
      }
    }

    /*
      Warning: used in mock tests.
    */
    protected function writeFile($path, $contents) {
      return file_put_contents($path, $contents);
    }
  }

?>