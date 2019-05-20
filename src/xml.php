<?php
  class XMLLogic {

    function __construct($page) {
      // doing xml
      $opts = array('output-xhtml' => true,
          'numeric-entities' => true);
      $this->xml = $this->utf8ForXml(tidy_repair_string($page, $opts));
      if ($this->xml === null) {
         throw new Exception("XMLLogic::invalid xml");
      }
      $this->doc = new DOMDocument();
      $this->doc->loadXML($this->xml);
      $this->xpath = new DOMXPath($this->doc);
      $this->xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');
    }

    function xPathRequest(string $que) {
      return $this->xpath->query($que);
    }

    /*
      Not all utf8 characters are valid in xml.
      So we need to replace some of them.
    */
    function utf8ForXml(string $string)
    {
        return preg_replace ('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }
  }

?>