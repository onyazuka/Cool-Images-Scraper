# Cool-Images-Scraper
php images scraper

## Description
Php script for scraping web images.

## Usage
You should pass in script's argv location of options file, which should be written in json.
Basically, you can do this in this way:

imgscrp.php options.json

## Options
- urls [array], MANDATORY - list of urls to scrap from;
- outputDir [string], MANDATORY - directory, in which downloaded images will be stored. Must already exist;
- recursive [boolean] - if set, walks by all available urls (in a href=...), if not, grabs images only from this page;
- depth [integer] - maximum level of recursion;
- whiteList [array of regexp] - patterns of allowed urls;
- blackList [array of regexp] - patterns of disallowed urls;
- path [array of regex] - pattern of scraper's work path. If set, the scraper works in recursive mode, with depth = count($options['path']). 
  So, on first level of recursion, path[0] is used, on second path[1]...;
- imageNamePatters [array of regex] - array of correct names of images, that can be saved;
- maxImageSize [numeric] - if image file not satisfies this condition, it will not be saved;
- minImageSize [numeric] - if image file not satisfies this condition, it will not be saved;
- fileRewrite [bool] - if set and true, rewrites file in 'outputDir' on conflict, else skips new file, preserving old.

## Examples
Examples of configuration files can be viewed in options%.json files.

## Todo
Cookies, JS support(if this can be done).
