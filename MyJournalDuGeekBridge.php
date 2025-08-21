<?php
class MyJournalDuGeekBridge extends FeedExpander {
    const MAINTAINER = 'vincent-helper';
    const NAME = 'Journal du Geek (full text)';
    const URI = 'https://www.journaldugeek.com/';
    const DESCRIPTION = 'Articles complets depuis le flux officiel de Journal du Geek';
    const PARAMETERS = [[]];
    const CACHE_TIMEOUT = 7200;

    public function collectData() {
        // On part du flux officiel
        $this->collectExpandableDatas(self::URI . 'feed/');
    }

    protected function parseItem($item) {
        $item = parent::parseItem($item);
        $url = $item['uri'] ?? $item['uri'] ?? null;
        if (!$url) return $item;

        $article = getSimpleHTMLDOMCached($url, self::CACHE_TIMEOUT);
        if (!$article) return $item;

        // Liste de sélecteurs plausibles pour le corps d’article sur JdG
        // (on essaie dans l’ordre, on garde le premier qui matche)
        $selectors = [
            'article',                // conteneur principal
            '.entry-content',         // patron WordPress classique
            '.post-content',          // fallback
            '.article__content',      // fallback
            '.content'                // fallback
        ];

        $contentNode = null;
        foreach ($selectors as $sel) {
            $found = $article->find($sel, 0);
            if ($found) { $contentNode = $found; break; }
        }
        if (!$contentNode) return $item;

        // Nettoyage : on supprime ce qui n’est pas du contenu éditorial
        foreach ([
            'script','style','noscript','iframe','form',
            '.share','.social','.newsletter','.comments',
            '.related','.tags','.author','.breadcrumbs',
            'header','footer','nav','.sidebar','aside'
        ] as $bad) {
            foreach ($contentNode->find($bad) as $node) $node->outertext = '';
        }

        // Normalisation des URL relatives
        defaultLinkTo($contentNode, self::URI);

        // Optionnel : retirer les attributs data-* lourds
        foreach ($contentNode->find('*[data-]') as $n) {
            // simple_html_dom ne gère pas bien la suppression wildcard d’attributs,
            // on enlève les plus communs :
            $n->removeAttribute('data-src');
            $n->removeAttribute('data-lazy');
            $n->removeAttribute('data-amp-auto-lightbox-disable');
        }

        // Si JdG met le texte dans un sous-conteneur, on peut affiner ici :
        // ex: $main = $contentNode->find('.post-content, .entry-content', 0) ?? $contentNode;

        $item['content'] = $contentNode->innertext;

        // Image d’illustration (si présente dans l’article)
        $img = $contentNode->find('img', 0);
        if ($img && empty($item['enclosures'])) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if ($src) $item['enclosures'] = [defaultLinkTo($src, self::URI)];
        }

        return $item;
    }

    // On laisse RSS-Bridge parser le flux XML d’origine
    protected function parseItemFromFeed($feedItem) {
        return $this->parseFeedItem($feedItem);
    }
}
