<?php

class MediapartBridge extends FeedExpander
{
    const MAINTAINER = 'killruana';
    const NAME = 'Mediapart Bridge';
    const URI = 'https://www.mediapart.fr/';
    const PARAMETERS = [[
        'single_page_mode' => [
            'name' => 'Single page article',
            'type' => 'checkbox',
            'title' => 'Display long articles on a single page',
            'defaultValue' => 'checked'
        ],
        'mpsessid' => [
            'name' => 'MPSESSID',
            'type' => 'text',
            'title' => 'Value of the session cookie MPSESSID'
        ]
    ]];
    const CACHE_TIMEOUT = 7200; // 2h
    const DESCRIPTION = 'Returns the newest articles.';

    public function collectData()
    {
        $url = self::URI . 'articles/feed';
        $this->collectExpandableDatas($url);
    }

    protected function parseItem(array $item)
    {
        $itemUrl = $item['uri'];

        // On ne traite que les contenus du journal
        if (strpos($itemUrl, self::URI . 'journal/') !== 0) {
            return $item;
        }

        // Lien en mode page unique pour l'utilisateur (option d’affichage)
        if ($this->getInput('single_page_mode') === true) {
            // éviter le double "?" si l’URL en contient déjà un
            $item['uri'] .= (str_contains($item['uri'], '?') ? '&' : '?') . 'onglet=full';
        }

        // Si un cookie de session est fourni, tenter d’extraire l’article complet
        $mpsessid = trim((string)$this->getInput('mpsessid') ?? '');
        if ($mpsessid !== '') {
            $opt = [
                CURLOPT_COOKIE => 'MPSESSID=' . $mpsessid,
                // Un UA explicite aide parfois à éviter un 403/CDN
                CURLOPT_USERAGENT => 'Mozilla/5.0 (RSS-Bridge; +https://github.com/RSS-Bridge/rss-bridge)'
            ];

            $pageUrl = $itemUrl . (str_contains($itemUrl, '?') ? '&' : '?') . 'onglet=full';
            $articlePage = getSimpleHTMLDOM($pageUrl, [], $opt); // wrapper officiel RSS-Bridge :contentReference[oaicite:1]{index=1}

            // Si le fetch échoue, on ne tente pas de parser
            if (!$articlePage) {
                return $item;
            }

            // Sélecteurs de repli : le DOM de Mediapart varie selon le type d’article/abonnement
            $selectors = [
                'div.content-article',
                'div.article-content',
                'article .content-article',
                'article .article-body',
                'div#article .content'
            ];

            $node = null;
            foreach ($selectors as $sel) {
                $node = $articlePage->find($sel, 0);
                if ($node && trim((string)$node->innertext) !== '') {
                    break;
                }
            }

            // Si rien de probant trouvé, on évite sanitize('') qui lève l’Exception
            if ($node && trim((string)$node->innertext) !== '') {
                $content = (string)$node->innertext;

                // Nettoyage + réécriture des liens vers URI de base
                $content = sanitize($content);                   // ne pas appeler sur chaîne vide
                $content = defaultLinkTo($content, static::URI);

                // Concaténer proprement même si 'content' est absent
                $item['content'] = ($item['content'] ?? '') . $content;
            }
        }

        return $item;
    }
}
