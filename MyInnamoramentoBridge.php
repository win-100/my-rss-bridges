<?php
class MyInnamoramentoBridge extends BridgeAbstract
{
    const MAINTAINER = 'vincent+chatgpt';
    const NAME = 'Innamoramento – Fil actu';
    const URI = 'https://www.innamoramento.net/';
    const DESCRIPTION = 'Récupère les actus (texte + image) depuis la page d’accueil';
    const CACHE_TIMEOUT = 3600; // 1h

    const PARAMETERS = [
        [
            'limit' => [
                'name' => 'Nombre d’items',
                'type' => 'number',
                'defaultValue' => 10
            ]
        ]
    ];

    public function collectData()
    {
        $html = getSimpleHTMLDOM(self::URI)
            or returnServerError('Impossible de charger la page');

        // Chaque "actu" est un <li class="actu ..."> dans <ul id="res_contenu">
        foreach ($html->find('section.blockzone ul#res_contenu li.actu') as $li) {
            // Ignore les messages perso de type "mp"
            if (strpos($li->class, 'mp') !== false) {
                continue;
            }

            $resumeA = $li->find('div.resume-container a.resume', 0);
            if (!$resumeA) {
                continue;
            }

            // Lien principal (absolu)
            $uri = $this->toAbsolute((string)$resumeA->href);

            // Titre “catégorie” (Actualité / Photos / Anniversaire / Rétrospective…)
            $titleBadge = $resumeA->find('span.title', 0);
            $titleLabel = $titleBadge ? trim(html_entity_decode($titleBadge->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';

            // Ligne infos (souvent "Auteur JJ/MM/AAAA HHhMM" ou juste "JJ/MM/AAAA")
            $infosSpan = $resumeA->find('span.infos', 0);
            $infosText = $infosSpan ? trim(html_entity_decode($infosSpan->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';

            // Texte du résumé (on enlève le titre et les infos de la version “plaintext”)
            $fullText = trim(html_entity_decode($resumeA->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($titleLabel !== '') {
                $fullText = trim(str_replace($titleLabel, '', $fullText));
            }
            if ($infosText !== '') {
                $fullText = trim(str_replace($infosText, '', $fullText));
            }

            // Image : on privilégie le lien du <a class="visuels" href="..."> (grande image)
            $visuelA = $li->find('a.visuels', 0);
            $imageHref = null;
            $imageThumb = null;
            if ($visuelA) {
                if (!empty($visuelA->href)) {
                    $imageHref = $this->toAbsolute((string)$visuelA->href);
                }
                $imgTag = $visuelA->find('img', 0);
                if ($imgTag && !empty($imgTag->src)) {
                    $imageThumb = $this->toAbsolute((string)$imgTag->src);
                }
            }

            // Timestamp + auteur depuis $infosText
            [$timestamp, $author] = $this->parseInfos($infosText);

            // Titre final de l’item :
            // - si titleLabel existe => "Actualité – ..." (texte résumé tronqué)
            // - sinon => le début du contenu
            $itemTitle = $titleLabel !== '' ? $titleLabel . ' – ' . $this->shorten($fullText, 100) : $this->shorten($fullText, 100);

            // Contenu HTML (inclut l’image si présente)
            $content = '';
            if ($imageHref) {
                // Image cliquable vers la grande version
                $content .= sprintf('<p><a href="%s"><img src="%s" alt=""></a></p>',
                    htmlspecialchars($imageHref), htmlspecialchars($imageThumb ?: $imageHref)
                );
            } elseif ($imageThumb) {
                $content .= sprintf('<p><img src="%s" alt=""></p>', htmlspecialchars($imageThumb));
            }
            $content .= '<p>' . htmlspecialchars($fullText) . '</p>';

            $item = [
                'uri'        => $uri,
                'title'      => $itemTitle,
                'content'    => $content,
                'author'     => $author ?: null,
                'timestamp'  => $timestamp ?: null,
            ];

            if ($imageHref) {
                // Enclosure pour les lecteurs qui gèrent les médias
                $item['enclosures'] = [ $imageHref ];
            }

            $this->items[] = $item;

            // Respect du paramètre limit
            if (count($this->items) >= (int)$this->getInput('limit')) {
                break;
            }
        }
    }

    private function toAbsolute(string $url): string
    {
        return urljoin(self::URI, $url);
    }

    private function shorten(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    /**
     * Extrait timestamp (Europe/Paris) et auteur depuis la chaîne d’infos.
     * Exemples d’inputs :
     *   "le-monde-mf 21/08/2025 11h33"
     *   "PtitGénie 21/08/2025 09h27"
     *   "21/08/2025"
     */
    private function parseInfos(string $infos): array
    {
        $infos = trim($infos);
        if ($infos === '') {
            return [null, null];
        }

        $author = null;
        $timestamp = null;

        // Cherche une date JJ/MM/AAAA + heure optionnelle HHhMM
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})(?:\s+(\d{2})h(\d{2}))?/', $infos, $m)) {
            $dateStr = $m[1]; // JJ/MM/AAAA
            $h = isset($m[2]) ? (int)$m[2] : 0;
            $min = isset($m[3]) ? (int)$m[3] : 0;

            // Auteur = ce qui précède la date (si présent)
            $pos = strpos($infos, $dateStr);
            if ($pos !== false && $pos > 0) {
                $authorCandidate = trim(mb_substr($infos, 0, $pos));
                if ($authorCandidate !== '') {
                    $author = $authorCandidate;
                }
            }

            // Crée un timestamp en Europe/Paris
            $tz = new \DateTimeZone('Europe/Paris');
            $dt = \DateTime::createFromFormat('d/m/Y H:i', sprintf('%s %02d:%02d', $dateStr, $h, $min), $tz);
            if ($dt !== false) {
                $timestamp = $dt->getTimestamp();
            }
        } else {
            // Parfois, il n’y a que la date
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $infos, $m)) {
                $dateStr = $m[1];
                $tz = new \DateTimeZone('Europe/Paris');
                $dt = \DateTime::createFromFormat('d/m/Y H:i', $dateStr . ' 00:00', $tz);
                if ($dt !== false) {
                    $timestamp = $dt->getTimestamp();
                }
                $pos = strpos($infos, $dateStr);
                if ($pos !== false && $pos > 0) {
                    $authorCandidate = trim(mb_substr($infos, 0, $pos));
                    if ($authorCandidate !== '') {
                        $author = $authorCandidate;
                    }
                }
            }
        }

        return [$timestamp, $author];
    }
}
