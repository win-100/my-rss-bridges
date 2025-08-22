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
        $url = $item['uri'] ?? null;
        if (!$url) return $item;

        $html = getSimpleHTMLDOMCached($url, self::CACHE_TIMEOUT);
        if (!$html) return $item;

        // 1) cibler le cœur d’article
        $content = $html->find('.entry-content', 0)
                ?? $html->find('article .entry-content', 0)
                ?? $html->find('article', 0)
                ?? $html->find('.post-content', 0);
        if (!$content) return $item;

        // 2) retirer ce qui n’est pas éditorial (dans entry-content)
        $kill = [
            // pubs OptiDigital
            '.od-wrapper', '[id^=optidigital-adslot-]',
            // promos / blocquote éditorial JdG (Google News / WhatsApp / newsletter)
            'blockquote',
            // formulaires / widgets
            'form', '#js-alertform', '[data-tmp-spotim-module]',
            // liens internes automatiques (par ex. "les dernières actualités" – normalement hors entry-content,
            // mais on les retire si jamais ils s’y glissent un jour)
            '.section-title', '.mt-4.grid', 'a[rel=bookmark]',
            // si jamais ils injectent des modules "source" et "tags" à l’intérieur
            '.post-source', '.post-tags'
        ];
        foreach ($kill as $css) {
            foreach ($content->find($css) as $n) {
                $n->outertext = '';
            }
        }

        // 3) alléger les images (garder src/alt/width/height)
        foreach ($content->find('img') as $img) {
            // si l’image n’a pas de src mais un data-src
            if (!$img->getAttribute('src')) {
                $dataSrc = $img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src');
                if ($dataSrc) $img->setAttribute('src', $dataSrc);
            }
            foreach (['srcset','sizes','loading','decoding','fetchpriority','class','style','data-src','data-lazy-src'] as $att) {
                $img->removeAttribute($att);
            }
        }

        // 4) virer les iframes/scripts/styles et autres bruits
        foreach (['script','style','noscript','iframe','nav','aside'] as $tag) {
            foreach ($content->find($tag) as $n) {
                $n->outertext = '';
            }
        }

        // 5) normaliser les liens relatifs (images + ancres)
        defaultLinkTo($content, self::URI);

        // 6) (optionnel) retirer classes & data-* pour alléger encore
        foreach ($content->find('[class]') as $n) $n->removeAttribute('class');
        foreach ($content->find('*[data-]') as $n) {
            // simple_html_dom ne supporte pas le wildcard, on enlève les plus courants
            foreach (array_keys($n->getAllAttributes() ?? []) as $a) {
                if (strpos($a, 'data-') === 0) $n->removeAttribute($a);
            }
        }

        // 7) contenu final
        $item['content'] = $content->innertext;

        // 8) enclosure (image d’entête si dispo)
        if (empty($item['enclosures'])) {
            $lead = $content->find('img', 0);
            if ($lead) {
                $src = $lead->getAttribute('src');
                if ($src) $item['enclosures'] = [defaultLinkTo($src, self::URI)];
            }
        }

        return $item;
    }

    // On laisse RSS-Bridge parser le flux XML d’origine
    protected function parseItemFromFeed($feedItem) {
        return $this->parseFeedItem($feedItem);
    }
}
