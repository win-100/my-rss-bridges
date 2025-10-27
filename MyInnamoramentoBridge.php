<?php
class MyInnamoramentoBridge extends BridgeAbstract
{
    const MAINTAINER = 'vincent+chatgpt';
    const NAME = 'Innamoramento – Fil actu';
    const URI = 'https://www.innamoramento.net/';
    const DESCRIPTION = 'Actus depuis la home (supporte cookies login).';
    const CACHE_TIMEOUT = 600; // 10 min

    const PARAMETERS = [[
        'limit' => [
            'name' => 'Nombre d’items',
            'type' => 'number',
            'defaultValue' => 10
        ],
        'use_auth' => [
            'name' => 'Utiliser mes cookies (après login)',
            'type' => 'checkbox',
            'defaultValue' => 'unchecked',
        ],
        'cookie' => [
            'name' => 'Cookie (en-tête HTTP complet)',
            'type' => 'text',
            'title' => 'Ex: PHPSESSID=...; innakeyy=...; innaid=...; innasess=...',
        ],
        'include_categories' => [
            'name' => 'Catégories à garder (ex: Anecdote,Actualité) (prioritaire sur exclusion)',
            'type' => 'text',
            'defaultValue' => ''
        ],
        'exclude_categories' => [
            'name' => 'Catégories à exclure (ex: Anniversaire,Photo du jour) (ignoré si inclusion utilisée)',
            'type' => 'text',
            'defaultValue' => ''
        ],
    ]];

    public function collectData()
    {
        // mêmes headers que le DIAG v2.2 (stables sur toutes versions)
        $headers = [
            'Referer: ' . self::URI,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9',
        ];
        $cookieHeaderRaw = '';
        if (!empty($this->getInput('use_auth'))) {
            $cookieHeaderRaw = trim((string)$this->getInput('cookie'));
            if ($cookieHeaderRaw !== '') {
                $headers[] = 'Cookie: ' . $cookieHeaderRaw;
            }
        }

        // charge le HTML "brut" puis parse (comme le DIAG qui marche)
        $raw = getContents(self::URI, $headers);
        if ($raw === false || $raw === null) {
            returnServerError('Impossible de charger la page');
        }
        $html = str_get_html($raw);
        if (!$html) {
            returnServerError('Le HTML n’a pas pu être parsé');
        }

        // filtre catégories optionnel
        $includecatFilter = array_filter(array_map('trim', explode(',', (string)$this->getInput('include_categories'))));
        $excludecatFilter = array_filter(array_map('trim', explode(',', (string)$this->getInput('exclude_categories'))));
        $max = (int)$this->getInput('limit');
        $count = 0;

        foreach ($html->find('section.blockzone ul#res_contenu li.actu') as $li) {
            $classes = $li->getAttribute('class') ?? '';
            if (strpos($classes, 'mp') !== false) {
                continue; // ignore le MP
            }

            $resumeA = $li->find('a.resume', 0) ?: $li->find('div.resume-container a.resume', 0);
            $visuelA = $li->find('a.visuels', 0);
            if (!$resumeA && !$visuelA) {
                continue;
            }

            // Titre/catégorie + infos (auteur/date)
            $titleLabel = '';
            $infosText = '';
            if ($resumeA) {
                $tb = $resumeA->find('span.title', 0);
                $titleLabel = $tb ? trim(html_entity_decode($tb->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
                // catégorie = ce qui est avant " - " dans le titre (ou vide)
                $cat = '';
                if (strpos($titleLabel, ' - ') !== false) {
                    $cat = mb_substr($titleLabel, 0, mb_strpos($titleLabel, ' - '));
                }
                $is = $resumeA->find('span.infos', 0);
                $infosText = $is ? trim(html_entity_decode($is->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
            }

            // filtre include_categories si demandé
            $ok=true;
            if ($cat !== '' && $titleLabel !== '') {
                if (!empty($includecatFilter)) {
                    $ok = false;
                    foreach ($includecatFilter as $wanted) {
                        if (mb_strtolower($cat) === mb_strtolower($wanted)) { $ok = true; break; }
                    }
                }
                else {
                    if (!empty($excludecatFilter)) {
                        foreach ($excludecatFilter as $unwanted) {
                            if (mb_strtolower($cat) === mb_strtolower($unwanted)) { $ok = false; break; }
                        }
                    }
                }
            }
            if (!$ok) {
                continue;
            }

            // lien principal
            $uri = null;
            if ($resumeA && !empty($resumeA->href)) {
                $uri = urljoin(self::URI, (string)$resumeA->href);
            } elseif ($visuelA && !empty($visuelA->href)) {
                $uri = urljoin(self::URI, (string)$visuelA->href);
            } else {
                continue;
            }

            // texte du résumé (on retire titre + infos)
            $fullText = '';
            if ($resumeA) {
                $fullText = trim(html_entity_decode($resumeA->plaintext, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($titleLabel !== '') $fullText = trim(str_replace($titleLabel, '', $fullText));
                if ($infosText !== '')  $fullText = trim(str_replace($infosText,  '', $fullText));
            } else {
                $fullText = $titleLabel ?: 'Voir le contenu';
            }

            // image & miniature
            $imageHref  = ($visuelA && !empty($visuelA->href)) ? urljoin(self::URI, (string)$visuelA->href) : null; // peut être une image OU une page (galerie)
            $imgTag     = $visuelA ? $visuelA->find('img', 0) : null;
            $imageThumb = ($imgTag && !empty($imgTag->src)) ? urljoin(self::URI, (string)$imgTag->src) : null;

            // auteur + timestamp
            [$ts, $author] = $this->parseInfos($infosText);

            // titre final
            $itemTitle = $titleLabel !== '' ? $titleLabel . ' – ' . $this->shorten($fullText, 100) : $this->shorten($fullText, 100);

            // contenu HTML
            $content = '';
            if ($imageHref) {
                $content .= sprintf('<p><a href="%s"><img src="%s" alt=""></a></p>',
                    htmlspecialchars($imageHref), htmlspecialchars($imageThumb ?: $imageHref));
            } elseif ($imageThumb) {
                $content .= sprintf('<p><img src="%s" alt=""></p>', htmlspecialchars($imageThumb));
            }
            $content .= '<p>' . htmlspecialchars($fullText) . '</p>';

            $item = [
                'uri'       => $uri,
                'title'     => $itemTitle,
                'content'   => $content,
                'author'    => $author ?: null,
                'timestamp' => $ts ?: null,
            ];
            // enclosure uniquement si href pointe vers une image directe
            if ($imageHref && preg_match('~\.(jpg|jpeg|png|webp|gif)(\?.*)?$~i', $imageHref)) {
                $item['enclosures'] = [ $imageHref ];
            }

            $this->items[] = $item;
            if (++$count >= $max) break;
        }
    }

    private function shorten(string $text, int $max): string
    {
        return (mb_strlen($text) <= $max) ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    private function parseInfos(string $infos): array
    {
        $infos = trim($infos);
        if ($infos === '') return [null, null];

        $author = null; $timestamp = null;
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})(?:\s+(\d{2})h(\d{2}))?/', $infos, $m)) {
            $dateStr = $m[1]; $h = isset($m[2]) ? (int)$m[2] : 0; $min = isset($m[3]) ? (int)$m[3] : 0;
            $pos = strpos($infos, $dateStr);
            if ($pos !== false && $pos > 0) {
                $a = trim(mb_substr($infos, 0, $pos)); if ($a !== '') $author = $a;
            }
            $tz = new \DateTimeZone('Europe/Paris');
            $dt = \DateTime::createFromFormat('d/m/Y H:i', sprintf('%s %02d:%02d', $dateStr, $h, $min), $tz);
            if ($dt !== false) $timestamp = $dt->getTimestamp();
        } elseif (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $infos, $m)) {
            $dateStr = $m[1];
            $tz = new \DateTimeZone('Europe/Paris');
            $dt = \DateTime::createFromFormat('d/m/Y H:i', $dateStr . ' 00:00', $tz);
            if ($dt !== false) $timestamp = $dt->getTimestamp();
            $pos = strpos($infos, $dateStr);
            if ($pos !== false && $pos > 0) {
                $a = trim(mb_substr($infos, 0, $pos)); if ($a !== '') $author = $a;
            }
        }
        return [$timestamp, $author];
    }
}
