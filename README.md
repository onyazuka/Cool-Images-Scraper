# Cool-Images-Scraper
php images scraper

## Description
Php script for scraping web images.

## Usage
The script accepts one argument(in argv) - options file(.json):

main.php options.json

## Options
- urls [array], MANDATORY - list of urls to scrap from;
- outputDir [string or array], MANDATORY - directory or directories list, in which downloaded images will be stored;
- recursive [boolean] - if set, walks by all available urls (in a href=...), if not, grabs images only from this page;
- depth [integer] - maximum level of recursion;
- whiteList [array of regexp] - patterns of allowed urls;
- blackList [array of regexp] - patterns of disallowed urls;
- path [array of regex] - pattern of scraper's work path. If set, the scraper works in recursive mode, with depth = count($options['path']). 
  So, on the first level of recursion, path[0] is used, on the second - path[1]...;
- imageNamePatters [array of regex] - array of patterns of images' names, that should be saved;
- maxImageSize [numeric] - if an image file don't satisfies this condition, it will not be saved;
- minImageSize [numeric] - if an image file don't satisfies this condition, it will not be saved;
- fileRewrite [bool] - if set and true, rewrites file in 'outputDir' on conflict, else skips new file, preserving old;
- createDirIfNotExists [string] - ONE OF 'simple' or 'recursive', creates outputDir if it not exists;
- initCookies[array or string] - either key-value array, or string in format "remixflash=32.0.0; remixscreen_depth=24;";
- cookieJar [string] - file name in which session cookies will be stored;
- additionalHeaders [array] - must be in format array('Content-type: text/plain', 'Content-length: 100').

## Examples
You can view the examples of configuration files at examples/options%.json.

## Todo
Multithread, JS support(if this can be done).
