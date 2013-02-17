<?php
class Url {
    private $_address;
    private $_protocol = false;
    private $_subdomain = false;
    private $_domain = false;
    private $_zone = false;
    private $_hostname = false;
    private $_request = false;

    public function __construct($address)
    {
        $this->_address = $address;
        return $this->_explain();
    }

    /**
     * Returns $this object on success and false if it doesn`t know
     * how to explain given address.
     * @return Url|boolean $this on success, false otherwise
     * @author 
     **/
    private function _explain()
    {
        if(preg_match_all('%((http:|https:)//([^."<>\n]+\.)?([^/."<>\n]*)\.([^.",<>\'/\n]+))(/[^",<>\'\n]*)?%', 
            $this->_address, $parts)) {
            $this->_hostname = $parts[1][0];
            $this->_protocol = $parts[2][0];
            $this->_subdomain = $parts[3][0];
            $this->_domain = $parts[4][0];
            $this->_zone = $parts[5][0];
            $this->_request = $parts[6][0]; 
        } else {
            if(preg_match_all('%^(/?[^\n]*)%m', $this->_address)) {
                $this->_request = $this->_address;
            } else {
                //  i`m should not explain anything
                //  if i do not know how
                return false;
            }
        }
        return $this;
    }

    /**
     * Returns true if address contains only requst.
     *
     * @return boolean 
     **/
    public function isRelative()
    {
        if($this->_address == $this->_request)
            return true;
        return false;
    }

    public function setHostname($hostname = '')
    {
        $this->_hostname = $hostname;
        return $this;
    }

    public function getHostname()
    {
        return $this->_hostname;
    }

    /**
     * Returns address. 
     *
     * @return string
     * @author 
     **/
    public function getAddress() {
        if($this->isRelative())
            return $this->_hostname . "/" . ltrim($this->_request, "/");
        return $this->_address;
    }

    public function toHash() {
        return md5($this->_address);
    }
}


/**
 * undocumented class
 *
 * @package default
 * @author 
 **/
class Nest
{
    const MAX_SPECIES = 1000;

    private $_baseUrl = null;
    private $_registry = array();
    private $_motherCrawler = null;
    private $_urlsGathered = array();
    private $_species_count = 0;
    private $_tree = array();

    function __construct($url = 'http://test.local') {
        $this->_baseUrl = new Url($url);

        if($this->_baseUrl) {
            $this->_motherCrawler = new Crawler($this->_baseUrl, $this);
            return $this;
        }

        return false;
    }

    public function getBaseHostname()
    {
        return $this->_baseUrl->getHostname();
    }

    public function getRegistry() {
        if(empty($this->_tree))
            $this->_build();
        return $this->_registry;
    }

    public function getTree() {
        if(empty($this->_tree))
            $this->_build();
        return $this->_tree;
    }

    private function _build()
    {
        $this->_tree = $this->_motherCrawler->hunt()->reproduce()->report(); 
        return $this;
    }

    public function registerUrl($url)
    {
        if(!$this->isRegistered($url))
            $this->_registry[$url->toHash()] = $url->getAddress();
        return $this;
    }

    public function isRegistered($url) {
        if(array_key_exists($url->toHash(), $this->_registry))
            return true;
        return false;
    }

    public function populationControl() {
        if($this->_species_count > $this::MAX_SPECIES)
            return true;
        return false;
    }

    public function crawlerBorn()
    {
        $this->_species_count++;
        echo "{$this->_species_count} crawlers in nest\n";
        return $this;
    }
} // END class Nest

/**
* Crawler class makes it all mapped
*/
class Crawler
{   
    private $_nest = null;
    private $_foundUrls = array();
    private $_crawlers = array();

    function __construct($url, $nest = null) {
        //echo "new crawler with url = {$ul";
        $this->_url = $url; 
        $this->_nest = $nest;
        $this->_nest->registerUrl($this->_url)->crawlerBorn();
        return $this;
    }

    public function hunt() 
    {
        $ch = curl_init($this->_url->getAddress());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        //    var_dump($response);
        curl_close($ch);

        if($response){
            if(preg_match_all('%<a href="([^"]*)".*>([^<]*)<\/a>%m', $response, $foundUrls)) {
                $this->_foundUrls= $foundUrls[1];
                //  var_dump($this->_foundUrls);
            }
        }

        return $this;
    }

    public function report()
    {
        $result = array();
        foreach($this->_crawlers as $crawler) {
            $result[] = $crawler->hunt()->reproduce()->report();
        }
        return array(
                $this->_url->getAddress() => $result, 
            );
    }

    public function reproduce() {
        if(!$this->_nest->populationControl())
            foreach($this->_foundUrls as $urlAddress) {
                $url = new Url($urlAddress);
                if($url->isRelative())
                    $url->setHostname($this->_url->getHostname());
                if((!$this->_nest->isRegistered($url)) && 
                    ($url->getHostname() == $this->_nest->getBaseHostname()))
                    $this->_crawlers[] = new Crawler($url, $this->_nest);
            }
        return $this;
    }

}

//  Scripting starts here...
if(function_exists('curl_init')) {
    $nest = new Nest('http://psi-logic.narod.ru');
    var_dump($nest->getTree());
    var_dump($nest->getRegistry());
}
?>
