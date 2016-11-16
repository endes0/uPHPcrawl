<?php
class uPHPcrawl {
    public $DOMcrawl       = true;
    public $REGEXcrawl     = true;
    public $extensions     = array();
    public $origin         = "";
    public $respectRobots  = true;
    public $pages          = array();
    private $disallow      = array();
    private $originContent = "";

    function uPHPcrawl($useDOM = true, $useREGEX = true, $Robotstxt = true) {
        $this->DOMcrawl      = $useDOM;
        $this->REGEXcrawl    = $useREGEX;
        $this->respectRobots = $Robotstxt;
    }

    function startCrawl ($originPage) {
        $this->pages         = array();
        $this->disallow      = array();
        $this->originContent = "";

        if (isset($originPage)) {
            $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"      => false,
                "verify_peer_name" => false,
                ),
            );

            $originContent = file_get_contents($originPage, false, stream_context_create($arrContextOptions));
            if (isset($originContent)) {
              $this->origin = $originPage;
              $url          = parse_url($this->origin);

              if ($this->respectRobots == true) {
                  $robots = file_get_contents($url['scheme'] . "://" . $url['host'] . "/robots.txt");
                  preg_match("(?:Disallow:)(.*)", $robots, $this->disallow);
              }

              if ($this->REGEXcrawl == true) {
                  $this->regexcrawl($originContent);
              }
              if ($this->DOMcrawl == true) {
                  $this->domcrawl($originContent, $this->origin);
              }
            } else {
              error_log("Url invalida.", 0);
            }
        } else {
            error_log("No se ha definido una pagina de origen.", 0);

        }
    }

    private function regexcrawl($Content) {
        preg_match_all('((?:www\.|http:\/\/|https:\/\/)\w+(?:\.\w+)+(?:[^\n()><\'",; ]*)*' . implode("|", $this->extensions) . ')', $Content, $urls);

        foreach($urls[0] as $url) {
            $url = $this->rebuild($url, $this->origin);

            if (!$this->isDisallowed($url, $this->disallow)) {
                if (!in_array($url, $this->pages)) {
                    array_push($this->pages, $url);
                }
            }
        }
    }

    private function domcrawl($Content, $origin) {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML($Content);

        foreach ($dom->getElementsByTagName('a') as $node) {
            $url = $node->getAttribute( 'href' );
            $url = $this->rebuild($url, $this->origin);

            if ($this->extensions) {
                if ($this->strposa($url, $this->extensions)) {
                    if (!$this->isDisallowed($url, $this->disallow)) {
                        if (!in_array($url, $this->pages)) {
                            array_push($this->pages, $url);
                        }
                    }
                }
            } else {
                if (!$this->isDisallowed($url, $this->disallow)) {
                    if (!in_array($url, $this->pages)) {
                        array_push($this->pages, $url);
                    }
                }
            }
        }
    }

    function rebuild($url, $origin) {
        error_reporting( error_reporting() & ~E_NOTICE );

        $temp = parse_url($origin);
        $out  = str_replace(array("\/", "/\\"), "/", $out);
        $out  = parse_url($this->addhttp($url));


        if ($out['host'] == null) {
            if (isset($out['query']) && isset($out['fragment'])) {
                return $temp['scheme'] . "://" . $temp['host'] . $out['path'] . "?" . $out['query'] . "#" . $out['fragment'];
            } elseif (isset($out['query'])) {
                return $temp['scheme'] . "://" . $temp['host'] . $out['path'] . "?" . $out['query'];
            } elseif (isset($out['fragment'])) {
                return $temp['scheme'] . "://" . $temp['host'] . $out['path'] . "#" . $out['fragment'];
            } else {
                return $temp['scheme'] . "://" . $temp['host'] . $out['path'];
            }

        } elseif ($out['scheme'] == null) {
            return $temp['scheme'] . "://" . $out['host'] . $out['path'] . "?" . $out['query'] . "#" . $out['fragment'];
        } else {
            return $url;
        }
    }

    private function isDisallowed($url, $disallow) {
        $url = parse_url($url);
        foreach($disallow as $not) {
            if($url['path'] == $not) {
                return true;
            }
        }
    }

    private function addhttp($url) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        return $url;
    }

    private function strposa($haystack, $needle, $offset=0) {
        if(!is_array($needle)) $needle = array($needle);
            foreach($needle as $query) {
                if(strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
            }
        return false;
    }
}

?>
