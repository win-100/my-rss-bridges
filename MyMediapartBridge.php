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
                return $item; // échec : garder le résumé
            }

            // Nouveaux gabarits Mediapart (selon ton extrait)
            $selectors = [
                'div.news__body__center__article.news__rich-text-content',
                'div.news__rich-text-content',
                'div.paywall-restricted-content.qiota_reserve',
                'main#main .news__rich-text-content',
                // anciens fallback :
                'div.content-article',
                'div.article-content',
                'article .article-body',
            ];

            $node = null;
            foreach ($selectors as $sel) {
                $node = $dom->find($sel, 0);
                if ($node && trim((string)$node->innertext) !== '') {
                    break;
                }
            }

            if ($node && trim((string)$node->innertext) !== '') {
                $html = (string)$node->innertext;
                $html = sanitize($html);
                // Base = URL de l'article (meilleure réécriture des liens relatifs)
                $html = defaultLinkTo($html, $itemUrl);
                // Remplacer le teaser par l’article complet
                $item['content'] = $html;
            }
        }

        return $item;
    }
}
