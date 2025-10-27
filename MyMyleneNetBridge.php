<?php

class MyMyleneNetBridge extends BridgeAbstract {
    const NAME = 'Mylene.net - breves';
    const URI = 'https://www.mylene.net/modules/lesbreves.php';
    const DESCRIPTION = 'Flux RSS des breves publiées sur mylene.net';
    const MAINTAINER = 'Win100';

    public function collectData() {
        $html = getSimpleHTMLDOM(self::URI)
            or returnServerError('Impossible de charger la page');
    
        $tweetWrappers = $html->find('div.tweet_wrapper');
    
        foreach ($tweetWrappers as $tweetWrapper) {
            $dateRaw = $tweetWrapper->find('div.tweet_date', 0)?->plaintext ?? '';
            $author = $tweetWrapper->find('div.tweet_author', 0)?->plaintext ?? 'Mylene.net';
    
            $contentBody = $tweetWrapper->find('div.tweet_content_body_in', 0);
            $content = $contentBody->innertext;
            $content = str_replace($contentBody->find('div.tweet_medias', 0)->innertext, '', $content);
    
            $mediaHtml = $tweetWrapper->find('div.tweet_medias', 0);
            if ($mediaHtml) {
                $mediaLink = $mediaHtml->find('a', 0);
                if ($mediaLink && strpos($mediaLink->href, 'youtu') !== false) {
                    $mediaHtml = '<a href="' . $mediaLink->href . '">Voir la vidéo sur YouTube</a>';
                } else {
                    $mediaHtml = $mediaHtml->innertext;
                }
            }
            
            $timestamp = $this->parseDateFr($dateRaw);
    
            $item = [];
            $title = trim($tweetWrapper->find('div.tweet_title', 0)?->plaintext ?? '');
            $item['title'] = $title;
            $item['author'] = htmlspecialchars(str_replace('Par ', '', $author));
            $item['uri'] = self::URI . '#b-' . md5($dateRaw . $author . $content);
            $item['timestamp'] = $timestamp;
            $item['content'] = $content . ($mediaHtml ? '<br/>Medias : ' . $mediaHtml : '');
    
            $this->items[] = $item;
        }
    }

    public function getURI() {
        return 'https://pasloin.fr/rss-bridge/?action=display&bridge=MyleneNet&format=Mrss';
    }
    

    private function parseDateFr($dateStr) {
        // Exemple : "Le 18/04/25 <br> à 17:05 </br>"
        $dateStr = strip_tags($dateStr);
        $dateStr = trim(str_replace(["Le", "à"], "", $dateStr));
        $dateStr = preg_replace('/\s+/', ' ', $dateStr); // nettoie les espaces

        // "18/04/25 17:05" → format FR
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{2}) (\d{2}):(\d{2})/', $dateStr, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3]; // suppose 20xx
            $hour = $matches[4];
            $min = $matches[5];
            return strtotime("$year-$month-$day $hour:$min");
        }

        return time(); // fallback
    }
}