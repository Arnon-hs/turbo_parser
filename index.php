<?php
use Symfony\Component\DomCrawler\Crawler;

mb_internal_encoding("UTF-8");
require('vendor/autoload.php');
define('DEFAULT_URL', "https://varmastroy.ru/");

function curlConnect($url = DEFAULT_URL){
    $curl = curl_init($url);
    // ПОДГОТОВКА ЗАГОЛОВКОВ
    $uagent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36";
    // ВСЯКИЕ ПАРАМЕТРЫ
    curl_setopt($curl, CURLOPT_USERAGENT, $uagent);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_COOKIE, "PMBC=96152e8e9a0168a731539c5e52c6b39a; PHPSESSID=jl0i13pn3157qca807jgp0jqa7; ServerName=WoW+Circle+3.3.5a+x5; serverId=1");
    $page = curl_exec($curl);
    curl_close($curl);

    return $page;
}

function findContent(Crawler $crawler){
    $crawler = $crawler->filter('main')->each(function (Crawler $node){
        $result['title'] = $node->filter('.h1')->text();
        try {
            $result['content'] = $node->filter('.content.content-txt')->each(function (Crawler $n){
                return $n->html();
            });
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    return $result;
    });
    return $crawler;
}

function sanitizeXML($xml_content, $xml_followdepth=true){

    if (preg_match_all('%<((\w+)\s?.*?)>(.+?)</\2>%si', $xml_content, $xmlElements, PREG_SET_ORDER)) {

        $xmlSafeContent = '';

        foreach($xmlElements as $xmlElem){
            $xmlSafeContent .= '<'.$xmlElem['1'].'>';
            if (preg_match('%<((\w+)\s?.*?)>(.+?)</\2>%si', $xmlElem['3'])) {
                $xmlSafeContent .= sanitizeXML($xmlElem['3'], false);
            }else{
                $xmlSafeContent .= htmlspecialchars($xmlElem['3'],ENT_NOQUOTES);
            }
            $xmlSafeContent .= '</'.$xmlElem['2'].'>';
        }

        if(!$xml_followdepth)
            return $xmlSafeContent;
        else
            return "<?xml version='1.0' encoding='UTF-8'?>".$xmlSafeContent;

    } else {
        return htmlspecialchars($xml_content,ENT_NOQUOTES);
    }
}

function putContent($content, $url){
    try{
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument();
        $xml->startElement("rss");
        $xml->writeAttribute("version", "2.0");
            $xml->startElement("channel");
                $xml->startElement("item");
                $xml->writeAttribute("turbo", "true");
                    foreach ($content as $item):
                        $xml->writeElement("title", $item[0]['title']);
                        $xml->writeElement("link", $url);
                        $xml->writeElement("turbo:topic", $item[0]['title']);
                        $xml->writeElement("turbo:source", $url);
                        $xml->writeElement("turbo:content", fuckingKostil($item[0]['content']));
                    endforeach;
                $xml->endElement();
            $xml->endElement();
        $xml->endElement();
//        file_put_contents('output.xml', $xml->outputMemory());
        $outFile = fopen("output.xml", "w") or die("Unable to open file!");
        fwrite($outFile, $xml->outputMemory());
        fclose($outFile);
    } catch (\Exception $e){
        return $e->getMessage();
    }
    return true;
}

function fuckingKostil($content){
    $out = '';
    foreach ($content as $item){
        $out .= $item . "\n";
    }
    return $out;
}


// START PROGRAM \\


//var_dump($page);
$page = curlConnect();
$crawler = new Crawler($page, null, DEFAULT_URL);
$crawler = $crawler->filter('.top-menu .menu-item-has-children .sub-menu li')->each(function (Crawler $node, $i) {
    $result["text"] = htmlspecialchars_decode($node->text(), ENT_HTML5);
    $result["link"] = $node->filter('a')->link()->getUri();
    return $result;
});
//var_dump($crawler);
array_pop($crawler);
foreach ($crawler as $key => $node){
    try {
//   echo  $node['link']." ". $node['text'] . "\n";
        $html = curlConnect($node['link']);
        $crawler = new Crawler($html, null, DEFAULT_URL);
        $content[] = findContent($crawler);

        if(putContent($content, $node['link']))
            echo "Success write!\n";
    } catch (\Exception $e){
        echo $e->getMessage();
    }
}