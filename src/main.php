<?php
  require_once "imgscrp.php";
  $before = microtime(true);
  $imgscrp = new ImageScraper($_SERVER['argv'][1]);
  $imgscrp->run();
  $after = microtime(true);
  print "Elapsed: " . ($after - $before) . "\n";
?>