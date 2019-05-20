<?php
  /*
    Singleten lookup dict,
    that checks visited urls, saved images etc...
  */
  class LookupDict {

    private static $instance;

    final private function __construct() 
    {
      $this->urls = [];
      $this->imgs = [];
    }

    final private function __clone() {}
    
    public static function get() {
      if(is_null(self::$instance)) {
        self::$instance = new static();
      }
      return self::$instance;
    }

    public function isUrlVisited(string $url) {
      return in_array($url, $this->urls);
    }

    public function isImageExists(string $imgName) {
      return in_array($imgName, $this->imgs);
    }

    public function setUrlVisited(string $url) {
      $this->urls[] = $url;
    }

    public function setImgExists(string $img) {
      $this->imgs[] = $img;
    }

    public function clear() {
      $this->urls = array();
      $this->imgs = array();
    }
  }

?>