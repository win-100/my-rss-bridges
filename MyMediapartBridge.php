<?php

class MyMediapartBridge extends FeedExpander
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

        // Ne traiter que les contenus du journal
        if (strpos($itemUrl, self::URI . 'journal/') !== 0) {
            return $item;
        }

        // Lien en mode page unique pour l’affichage utilisateur
        if ($this->getInput('single_page_mode') === true) {
            $item['uri'] .= (str_contains($item['uri'], '?') ? '&' : '?') . 'onglet=full';
        }

        // Essayer de charger l'article complet si un cookie est fourni
        $mpsessid = trim((string)($this->getInput('mpsessid') ?? ''));
        if ($mpsessid !== '') {
            $opts = [
                CURLOPT_COOKIE => 'MPSESSID=' . $mpsessid,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (RSS-Bridge; +https://github.com/RSS-Bridge/rss-bridge)',
            ];

            // Forcer la version "plein texte"
            $pageUrl = $itemUrl . (str_contains($itemUrl, '?') ? '&' : '?') . 'onglet=full';
            $dom = getSimpleHTMLDOM($pageUrl, [], $opts);
            if (!$dom) {
                return $item; // Échec du chargement : garder le résumé du flux
            }

            // Sélecteurs possibles selon le gabarit
            $selectors = [
                'div.content-article',
                'div.article-content',
                'article .content-article',
                'article .article-body',
                'div#article .content',
            ];

            $node = null;
            foreach ($selectors as $sel) {
                $node = $dom->find($sel, 0);
                if ($node && trim((string)$node->innertext) !== '') {
                    break;
                }
            }

            // Si on a trouvé du contenu exploitable, remplacer le teaser par l'article complet
            if ($node && trim((string)$node->innertext) !== '') {
                $html = (string)$node->innertext;
                $html = sanitize($html);
                $html = defaultLinkTo($html, static::URI);
                $item['content'] = $html;
            }
        }

        return $item;
    }
}
