<?php
/*
VistaPanel Users API library
Originally by @oddmario, maintained by @GenerateApps
*/
error_reporting(E_ERROR | E_PARSE);
class VistapanelApi
{
    
    public $cpanelUrl = "https://cpanel.spook.rr.nu";
    public $loggedIn = false;
    public $vistapanelSession = "";
    public $vistapanelSessionName = "PHPSESSID";
    public $vistapanelToken = 0;
    public $accountUsername = "";
    
    public function getLineWithString($content, $str)
    {
        $lines = explode("\n", $content);
        foreach ($lines as $lineNumber => $line) {
            if (strpos($line, $str) !== false) {
                return $line;
            }
        }
        return -1;
    }

    public function simpleCurl(
        $url = "",
        $post = false,
        $postfields = array(),
        $header = false,
        $httpheader = array(),
        $followlocation = false
    )
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'
        );
        if ($followlocation) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function classError($error)
    {
        die("VistapanelApi_error: " . $error);
    }
    
    private function checkCpanelUrl()
    {
        if (empty($this->cpanelUrl)) {
            $this->classError("Please set cpanelUrl first.");
        }
        if (substr($this->cpanelUrl, -1) == "/") {
            $this->cpanelUrl = substr_replace($this->cpanelUrl, "", -1);
        }
    }
    
    private function checkLogin()
    {
        $this->checkCpanelUrl();
        if (!$this->loggedIn) {
            $this->classError("Not logged in.");
        }
    }
    
    public function login($username, $password, $theme = "PaperLantern")
    {
        $this->checkCpanelUrl();
        if (!isset($username)) {
            $this->classError("username is required.");
        }
        if (!isset($password)) {
            $this->classError("password is required.");
        }
        $login = $this->simpleCurl($this->cpanelUrl . "/login.php", true, array(
            "uname" => $username,
            "passwd" => $password,
            "theme" => $theme,
            "seeesurf" => "567811917014474432"
        ), true, array(), true);
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $login, $matches);
        $cookies = array();
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        if (!empty($cookies['PHPSESSID'])) {
            if (strpos($login, "document.location.href = 'panel/indexpl.php") !== false) {
                if ($this->loggedIn !== true) {
                    $this->loggedIn           = true;
                    $this->accountUsername    = $username;
                    $this->vistapanelSession = $cookies[$this->vistapanelSessionName];
                    $this->vistapanelToken   = $this->getToken();
                    $checkImportantNotice     = $this->simpleCurl(
                        $this->cpanelUrl . "/panel/indexpl.php",
                        false,
                        array(),
                        false,
                        array(
                            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
                        )
                    );
                    if (!strpos(
                        $checkImportantNotice,
                        "To notify you of changes to service and offers we need permission to send you email")
                    )
                    {
                        $this->simpleCurl($this->cpanelUrl . "/panel/approve.php", true, array(
                            "submit" => true
                        ), false, array(
                            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
                        ));
                    }
                } else {
                    $this->classError("You are already logged in.");
                }
            } else {
                $this->classError("Invalid login credentials.");
            }
        } else {
            $this->classError("Unable to login.");
        }
    }
    
    public function createDatabase($dbname = "")
    {
        $this->checkLogin();
        if (empty($dbname)) {
            $this->classError("dbname is required.");
        }
        $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php?option=mysql&cmd=create", true, array(
            "db" => $dbname
        ), false, array(
            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
        ));
        return true;
    }

    public function uploadKey($domainname, $key, $csr)
    {
        $this->checkLogin();
        if (empty($domainname)) {
            $this->classError("domainname is required.");
            
        }
        if (empty($key)) {
            $this->classError("key is required.");
            
        }
        $this->simpleCurl($this->cpanelUrl . "/panel/modules-new/sslconfigure/uploadkey.php", true, array(
            "domain_name" => $domainname,
            "csr" => $csr,
            "key" => $key
            
        ), false, array(
            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
        ));
        return true;
    }

    public function uploadCert($domainname, $cert)
    {
        $this->checkLogin();
        if (empty($domainname)) {
            $this->classError("domainname is required.");
            
        }
        if (empty($cert)) {
            $this->classError("cert is required.");
            
        }
        $this->simpleCurl($this->cpanelUrl . "/panel/modules-new/sslconfigure/uploadcert.php", true, array(
            "domain_name" => $domainname,
            "cert" => $cert
            
        ), false, array(
            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
        ));
        return true;
    }

    public function listDatabases()
    {
        $this->checkLogin();
        $databases   = array();
        $htmlContent = $this->simpleCurl(
            $this->cpanelUrl . "/panel/indexpl.php?option=pma",
            false,
            array(),
            false,
            array(
                "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
            )
        );
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        $header = $dom->getElementsByTagName('th');
        $detail = $dom->getElementsByTagName('td');
        foreach ($header as $nodeHeader) {
            $aDataTableHeaderHTML[] = trim($nodeHeader->textContent);
        }
        $i = 0;
        $j = 0;
        foreach ($detail as $sNodeDetail) {
            $aDataTableDetailHTML[$j][] = trim($sNodeDetail->textContent);
            $i                          = $i + 1;
            $j                          = $i % count($aDataTableHeaderHTML) == 0 ? $j + 1 : $j;
        }
        for ($i = 0; $i < count($aDataTableDetailHTML); $i++) {
            for ($j = 0; $j < count($aDataTableHeaderHTML); $j++) {
                $aTempData[$i][$aDataTableHeaderHTML[$j]] = $aDataTableDetailHTML[$i][$j];
            }
        }
        $aDataTableDetailHTML = $aTempData;
        unset($aTempData);
        foreach ($aDataTableDetailHTML as $database) {
            $databases[str_replace($this->accountUsername . "_", "", array_shift($database))] = true;
        }
        return $databases;
    }
    
    public function deleteDatabase($database = "")
    {
        $this->checkLogin();
        if (empty($database)) {
            $this->classError("database is required.");
        }
        if (!in_array($database, $this->listDatabases())) {
            $this->classError("The database you're trying to remove doesn't exist.");
        }
        $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php?option=mysql&cmd=remove", true, array(
            "toremove" => $database,
            "Submit2" => "Remove Database"
        ), false, array(
            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
        ));
        return true;
    }
    
    public function getPhpmyadminLink($database = "")
    {
        $this->checkLogin();
        if (empty($database)) {
            $this->classError("database is required.");
        }
        if (!array_key_exists($database, $this->listDatabases())) {
            $this->classError("The database you're trying to get the PMA link of doesn't exist.");
        }
        $htmlContent = $this->simpleCurl(
            $this->cpanelUrl . "/panel/indexpl.php?option=pma",
            false,
            array(),
            false,
            array(
                "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
            )
        );
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            if (strpos($link->getAttribute('href'), "&db=" . $this->accountUsername . "_" . $database) !== false) {
                return $link->getAttribute('href');
            }
        }
    }
    
    public function getToken()
    {
        $this->checkLogin();
        $homepage = $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php", false, array(), false, array(
            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
        ));
        $json     = $this->getLineWithString($homepage, "/panel/indexpl.php?option=passwordchange&ttt=");
        $json     = substr_replace($json, "", -1);
        $json     = json_decode($json, true);
        $url      = $json['url'];
        return (int) filter_var($url, FILTER_SANITIZE_NUMBER_INT);
    }
    
    public function listAddonDomains()
    {
        $this->checkLogin();
        $addonDomains = array();
        $htmlContent  = $this->simpleCurl(
            $this->cpanelUrl . "/panel/indexpl.php?option=domains&ttt=" . $this->vistapanelToken,
            false,
            array(),
            false,
            array(
                "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
            )
        );
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        $header = $dom->getElementsByTagName('th');
        $detail = $dom->getElementsByTagName('td');
        foreach ($header as $nodeHeader) {
            $aDataTableHeaderHTML[] = trim($nodeHeader->textContent);
        }
        $i = 0;
        $j = 0;
        foreach ($detail as $sNodeDetail) {
            $aDataTableDetailHTML[$j][] = trim($sNodeDetail->textContent);
            $i                          = $i + 1;
            $j                          = $i % count($aDataTableHeaderHTML) == 0 ? $j + 1 : $j;
        }
        for ($i = 0; $i < count($aDataTableDetailHTML); $i++) {
            for ($j = 0; $j < count($aDataTableHeaderHTML); $j++) {
                $aTempData[$i][$aDataTableHeaderHTML[$j]] = $aDataTableDetailHTML[$i][$j];
            }
        }
        $aDataTableDetailHTML = $aTempData;
        unset($aTempData);
        foreach ($aDataTableDetailHTML as $addonDomain) {
            $addonDomains[array_shift($addonDomain)] = true;
        }
        return $addonDomains;
    }
    
    public function listSubDomains()
    {
        $this->checkLogin();
        $subDomains  = array();
        $htmlContent = $this->simpleCurl(
            $this->cpanelUrl . "/panel/indexpl.php?option=subdomains&ttt=" . $this->vistapanelToken,
            false,
            array(),
            false,
            array(
                "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
            )
        );
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        $header = $dom->getElementsByTagName('th');
        $detail = $dom->getElementsByTagName('td');
        foreach ($header as $nodeHeader) {
            $aDataTableHeaderHTML[] = trim($nodeHeader->textContent);
        }
        $i = 0;
        $j = 0;
        foreach ($detail as $sNodeDetail) {
            $aDataTableDetailHTML[$j][] = trim($sNodeDetail->textContent);
            $i                          = $i + 1;
            $j                          = $i % count($aDataTableHeaderHTML) == 0 ? $j + 1 : $j;
        }
        for ($i = 0; $i < count($aDataTableDetailHTML); $i++) {
            for ($j = 0; $j < count($aDataTableHeaderHTML); $j++) {
                $aTempData[$i][$aDataTableHeaderHTML[$j]] = $aDataTableDetailHTML[$i][$j];
            }
        }
        $aDataTableDetailHTML = $aTempData;
        unset($aTempData);
        foreach ($aDataTableDetailHTML as $subDomain) {
            $subDomains[array_shift($subDomain)] = true;
        }
        unset($subDomains[current(array_keys($subDomains))]);
        return $subDomains;
    }
    

    public function getSoftaculousLink()
    {
        $this->checkLogin();
        $getlink = $this->simpleCurl(
            $this->cpanelUrl . "/panel/indexpl.php?option=installer&ttt=" . $this->vistapanelToken,
            false,
            array(),
            true,
            array(
                "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
            ),
            true
        );
        if (preg_match('~Location: (.*)~i', $getlink, $match)) {
            $location = trim($match[1]);
        }
        return $location;
    }
    
 
    public function logout()
    {
        $this->checkLogin();
        $this->simpleCurl($this->cpanelUrl . "/panel/indexpl.php?option=signout", false, array(), false, array(
            "Cookie: " . $this->vistapanelSessionName . "=" . $this->vistapanelSession
        ), true);
        $this->cpanelUrl = "";
        $this->loggedIn = false;
        $this->vistapanelSession = "";
        $this->vistapanelToken = 0;
        $this->accountUsername = "";
        return true;
    }
}
