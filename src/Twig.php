<?php

declare(strict_types=1);

namespace App;

use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use App\Command\CommandBase;
use DateTime;
use DateTimeZone;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\RequestOptions;
use League\CommonMark\Environment\Environment as CommonMarkEnvironment;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\MarkdownConverter;
use Psr\Cache\CacheItemPoolInterface;
use Samwilson\CommonMarkLatex\LatexRendererExtension;
use Samwilson\CommonMarkShortcodes\Shortcode;
use Samwilson\CommonMarkShortcodes\ShortcodeExtension;
use Samwilson\PhpFlickr\PhotosApi;
use Samwilson\PhpFlickr\PhpFlickr;
use SimplePie\Item;
use SimplePie\SimplePie;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class Twig extends AbstractExtension
{
    /** @var Database */
    protected $db;

    /** @var Site */
    protected $site;

    /** @var Page */
    protected $page;

    /** @var mixed[] Runtime cache of API-retrieved info. */
    protected static $data = [
        'commons' => [],
        'wikidata' => [],
        'flickr' => [],
        'json' => [],
    ];

    public function __construct(Database $db, Site $site, Page $page)
    {
        $this->db = $db;
        $this->site = $site;
        $this->page = $page;
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('basename', 'basename'),
            new TwigFilter('dirname', 'dirname'),
            new TwigFilter('md2html', [$this, 'filterMarkdownToHtml']),
            new TwigFilter('md2latex', [$this, 'filterMarkdownToLatex']),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('instanceof', [$this, 'functionInstanceof']),
            new TwigFunction('date', 'date'),
            new TwigFunction('date_create', [$this, 'functionDateCreate']),
            new TwigFunction('strtotime', 'strtotime'),
            new TwigFunction('json_decode', 'json_decode'),
            new TwigFunction('get_json', [$this, 'functionGetJson']),
            new TwigFunction('get_feeds', [$this, 'functionGetFeeds']),
            new TwigFunction('tex_url', [$this, 'functionTexUrl']),
            new TwigFunction('wikidata', [$this, 'functionWikidata']),
            new TwigFunction('commons', [$this, 'functionCommons']),
            new TwigFunction('wikipedia', [$this, 'functionWikipedia']),
            new TwigFunction('wikidata_query', [$this, 'functionWikidataQuery']),
            new TwigFunction('commons_query', [$this, 'functionCommonsQuery']),
            new TwigFunction('flickr', [$this, 'functionFlickr']),
            new TwigFunction('qrcode', [$this, 'functionQrCode']),
        ];
    }

    public function filterMarkdownToHtml(string $input): string
    {
        $environment = $this->getCommonMarkEnvironment('html');
        $environment->addExtension(new AutolinkExtension());
        $converter = new MarkdownConverter($environment);
        return $converter->convert($input)->getContent();
    }

    public function filterMarkdownToLatex(string $input): string
    {
        $environment = $this->getCommonMarkEnvironment('tex');
        $environment->addExtension(new LatexRendererExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addEventListener(DocumentPreRenderEvent::class, function (DocumentPreRenderEvent $event): void {
            $filesystem = new Filesystem();
            foreach ($event->getDocument()->iterator() as $node) {
                if (!$node instanceof Image) {
                    continue;
                }
                if (substr($node->getUrl(), 0, 4) === 'http') {
                    // Absolute URLs.
                    $node->setUrl($this->functionTexUrl($node->getUrl()));
                } else {
                    // Relative URLs.
                    $dirname = dirname($node->getUrl());
                    $filename = basename($node->getUrl());
                    $pageDir = dirname($this->page->getId());
                    $pathTex = $this->site->getDir() . '/cache/tex' . $pageDir;
                    $pathContent = realpath($this->site->getDir() . '/content' . $pageDir . '/' . $dirname);
                    $pathContentFull = $pathContent . '/' . $filename;
                    if (!is_file($pathContentFull)) {
                        throw new Exception("Unable to find image: $pathContentFull");
                    }
                    $path = $filesystem->makePathRelative($pathContent, $pathTex) . $filename;
                    $node->setUrl($path);
                }
            }
        });
        $converter = new MarkdownConverter($environment);
        return $converter->convert($input)->getContent();
    }

    /**
     * @param string|DateTime $dateTime
     * @param string|DateTimeZone $timezone
     */
    public function functionDateCreate($dateTime, $timezone = 'Z'): DateTime
    {
        if (!$timezone instanceof DateTimeZone) {
            $timezone = new DateTimeZone((string) $timezone);
        }
        if (!$dateTime instanceof DateTime) {
            $dateTime = date_create($dateTime);
        }
        $dateTime->setTimezone($timezone);
        return $dateTime;
    }

    /**
     * @param mixed $a
     */
    public function functionInstanceof($a, string $b): bool
    {
        return $a instanceof $b;
    }

    /**
     * Download the remote file to a local path inside the tex/ directory, and return that path.
     *
     * @return string Full filesystem path to the downloaded file.
     */
    public function functionTexUrl(string $url): string
    {
        // Set up file and directory names.
        $filename = md5($url) . '.' . pathinfo($url, PATHINFO_EXTENSION);
        $outputFilepath = $this->site->getDir() . '/cache/tex/_urls/' . $filename;

        if (!file_exists($outputFilepath)) {
            CommandBase::writeln('TeX file download: ' . basename($url));
            Util::mkdir(dirname($outputFilepath));

            // Download to a local directory if it's not already there.
            if (!file_exists($outputFilepath) || !filesize($outputFilepath)) {
                try {
                    (new Client())->get($url, [RequestOptions::SINK => fopen($outputFilepath, 'w+')]);
                } catch (Throwable $exception) {
                    throw new Exception("Unable to download: $url");
                }
            }

            if (!file_exists($outputFilepath) || !filesize($outputFilepath)) {
                throw new Exception("Download failed: $url");
            }
        }

        // Return the relative path to the downloaded file.
        $depth = count(explode('/', $this->page->getId()));
        return str_repeat('../', $depth - 2) . '_urls/' . $filename;
    }

    /**
     * @return mixed[]
     */
    public function functionWikidata(string $wikidataId): array
    {
        if (isset(self::$data['wikidata'][$wikidataId])) {
            return self::$data['wikidata'][$wikidataId];
        }
        $cache = $this->getCachePool('wikidata');
        $cacheItem = $cache->getItem('wikidata' . $wikidataId);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        $api = $this->site->getMediawikiApi('https://www.wikidata.org/w/api.php');
        $request = ActionRequest::simpleGet('wbgetentities')
            ->setParam('ids', $wikidataId);
        CommandBase::writeln('Wikidata fetch info: ' . $wikidataId);
        $result = $api->request($request);
        self::$data['wikidata'][$wikidataId] = $result['entities'][$wikidataId];
        $cacheItem->set(self::$data['wikidata'][$wikidataId]);
        $cache->save($cacheItem);
        return self::$data['wikidata'][$wikidataId];
    }

    /**
     * @return string[][]
     */
    public function functionWikidataQuery(string $sparql): array
    {
        return (new WikidataQuery($sparql))->fetch();
    }

    /**
     * https://commons.wikimedia.org/wiki/Commons:SPARQL_query_service/API_endpoint
     *
     * @return string[][]
     */
    public function functionCommonsQuery(string $sparql): array
    {
        $cache = $this->getCachePool('commons_query');
        $cacheItem = $cache->getItem('commons_query_' . md5($sparql));
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        $client = new Client();
        $config = $this->site->getConfig();
        if (!isset($config->commons->wcqs_auth_token) || !$config->commons->wcqs_auth_token) {
            throw new Exception(
                "You must set `commons.wcqs_auth_token` in the site's config file."
                . ' See https://w.wiki/9jke for how to retrieve the value for it.'
            );
        }
        $cookie = new SetCookie([
            'Name' => 'wcqsOauth',
            'Value' => $config->commons->wcqs_auth_token,
            'Domain' => 'commons-query.wikimedia.org',
        ]);
        $requestOptions = [
            'query' => [
                'format' => 'json',
                'query' => $sparql,
            ],
            'cookies' => new CookieJar(true, [$cookie]),
        ];
        $response = $client->request('GET', 'https://commons-query.wikimedia.org/sparql', $requestOptions);
        $json = $response->getBody()->getContents();
        $data = json_decode($json, true);
        $out = [];
        foreach ($data['results']['bindings'] ?? [] as $binding) {
            $row = [];
            foreach ($binding as $name => $b) {
                $row[$name] = $b['value'];
            }
            $out[] = $row;
        }
        $cacheItem->set($out);
        $cache->save($cacheItem);
        return $out;
    }

    /**
     * @return string[]
     */
    public function functionFlickr(string $photoId): array
    {
        if (isset(self::$data['flickr'][$photoId])) {
            return self::$data['flickr'][$photoId];
        }
        $config = $this->site->getConfig()->flickr;
        $flickr = new PhpFlickr($config->api_key, $config->api_secret);
        $cache = $this->getCachePool('flickr');
        $cacheItem = $cache->getItem('flickr' . $photoId);
        if ($cacheItem->isHit()) {
            self::$data['flickr'][$photoId] = $cacheItem->get();
        } else {
            $flickr->setCache($cache);
            $shortUrl = $flickr->urls()->getShortUrl($photoId);
            CommandBase::writeln("Flickr fetch info: $photoId $shortUrl");
            $info = $flickr->photos()->getInfo($photoId);
            self::$data['flickr'][$photoId] = [
                'id' => $info['id'],
                'title' => $info['title'],
                'description' => $info['description'],
                'urls' => [
                    'photopage' => $info['urls']['url'][0]['_content'],
                    'short' => $shortUrl,
                    'medium_image' => $flickr->urls()->getImageUrl($info, PhotosApi::SIZE_MEDIUM_800),
                ],
                'dates' => $info['dates'],
                'owner' => $info['owner'],
                'license' => $flickr->photosLicenses()->getInfo()[$info['license']],
            ];
            $cacheItem->set(self::$data['flickr'][$photoId]);
            $cache->save($cacheItem);
        }

        return self::$data['flickr'][$photoId];
    }

    /**
     * @return mixed[]
     */
    public function functionCommons(string $filename): array
    {
        if (isset(self::$data['commons'][$filename])) {
            return self::$data['commons'][$filename];
        }
        $cache = $this->getCachePool('commons');
        $cacheItem = $cache->getItem('commons' . $filename);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        $api = $this->site->getMediawikiApi('https://commons.wikimedia.org/w/api.php');
        $fileInfoResponse = $api->request(ActionRequest::simpleGet('query')
            ->addParams([
                'prop' => 'imageinfo',
                'iiprop' => 'url',
                'iiurlwidth' => $this->site->getConfig()->embedWidth ?? 800,
                'titles' => 'File:' . $filename,
                'redirects' => true,
            ]));
        $fileInfo = array_shift($fileInfoResponse['query']['pages']);
        if (!isset($fileInfo['pageid'])) {
            throw new Exception('Commons file does not exist: ' . $filename);
        }
        CommandBase::writeln("Commons fetch info: $filename");
        $mediaInfoResponse = $api->request(ActionRequest::simpleGet('wbgetentities')
            ->addParams(['ids' => 'M' . $fileInfo['pageid']]));
        $mediaInfo = array_shift($mediaInfoResponse['entities']);
        self::$data['commons'][$filename] = array_merge($fileInfo, $mediaInfo);
        $cacheItem->set(self::$data['commons'][$filename]);
        $cache->save($cacheItem);
        return self::$data['commons'][$filename];
    }

    public function functionWikipedia(string $lang, string $articleTitle): string
    {
        $url = "https://$lang.wikipedia.org/api/rest_v1/page/summary/" . str_replace(' ', '_', $articleTitle);
        $response = $this->functionGetJson($url);
        if (!isset($response['extract_html'])) {
            throw new Exception("Unable to get extract of Wikipedia article: $articleTitle");
        }
        return $response['extract_html'];
    }

    public function functionGetJson(string $url): mixed
    {
        $cacheKey = md5($url);
        if (isset(self::$data['json'][$cacheKey])) {
            return self::$data['json'][$cacheKey];
        }
        $cache = $this->getCachePool('json_' . parse_url($url, PHP_URL_HOST));
        $cacheItem = $cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        CommandBase::writeln("Get JSON: $url");
        $json = (new Client())->get($url)->getBody()->getContents();
        $response = json_decode($json, true);
        if (!$response) {
            throw new Exception("Unable to get JSON from URL: $url");
        }
        self::$data['json'][$cacheKey] = $response;
        $cacheItem->set(self::$data['json'][$cacheKey]);
        $cache->save($cacheItem);
        return self::$data['json'][$cacheKey];
    }

    /**
     * @param string|string[] $feedUrls
     * @return ?Item[]
     */
    public function functionGetFeeds($feedUrls): ?array
    {
        $simplepie = new SimplePie();
        $simplepie->set_cache(new Psr16Cache($this->getCachePool('feeds')));
        $simplepie->set_feed_url($feedUrls);
        $simplepie->init();
        return $simplepie->get_items();
    }

    /**
     * @return string Relative URL string, of the form '/assets/qrcodes/hash.svg'.
     */
    public function functionQrCode(string $text): string
    {
        $qrFilename = md5($text) . '.svg';
        $assetPath = '/qrcodes/' . $qrFilename;
        $filePath = $this->site->getDir() . '/output' . $assetPath;
        $cachePath = $this->site->getDir() . '/cache/qrcodes/' . $qrFilename;
        // If it's already been used in this run, nothing neds to be done.
        if (file_exists($filePath)) {
            return $assetPath;
        }
        // Create the required directories.
        Util::mkdir(dirname($cachePath));
        Util::mkdir(dirname($filePath));
        // If it's already cached, copy it to output and use it.
        if (file_exists($cachePath)) {
            copy($cachePath, $filePath);
            return $assetPath;
        }
        // Create cached file.
        Builder::create()
            ->writer(new SvgWriter())
            ->data($text)
            ->build()
            ->saveToFile($cachePath);
        // Copy it to output.
        copy($cachePath, $filePath);
        return $assetPath;
    }

    /**
     * Escape a TeX string.
     *
     * @param string|null $string The string to escape.
     * @param string $charset The charset of the string. Not used.
     */
    public function escapeTex(?string $string = '', string $charset = 'utf-8'): string
    {
        if ($string === null) {
            return '';
        }
        $pat = [
            '/\\\(\s)/',
            '/\\\(\S)/',
            '/&/',
            '/%/',
            '/\$/',
            '/>>/',
            '/_/',
            '/\^/',
            '/#/',
            '/"(\s)/',
            '/"(\S)/',
        ];
        $rep = [
            '\textbackslash\ $1',
            '\textbackslash $1',
            '\&',
            '\%',
            '\textdollar ',
            '\textgreater\textgreater ',
            '\_',
            '\^',
            '\#',
            '\textquotedbl\ $1',
            '\textquotedbl $1',
        ];
        return preg_replace($pat, $rep, $string);
    }

    /**
     * Escape a CSV cell value by wrapping it in quotes if required, and escaping double-quotes.
     *
     * @param string|null $string The string to escape.
     * @param string $charset The charset of the string.
     */
    public function escapeCsv(?string $string = '', string $charset = 'utf-8'): string
    {
        if ($string === null) {
            return '';
        }
        $out = str_replace('"', '""', $string);
        if (strpos($out, '"') !== false || strpos($out, ',') !== false) {
            $out = '"' . $out . '"';
        }
        return $out;
    }

    private function getCommonMarkEnvironment(string $format): CommonMarkEnvironment
    {
        $shortcodes = [];
        foreach ($this->site->getTemplates($this->db, 'shortcodes') as $shortcodeTemplate) {
            $shortcodeName = substr($shortcodeTemplate->getName(), strlen('shortcodes/'));
            $page = $this->page;
            $shortcodes[$shortcodeName] = static function (
                Shortcode $shortcode
            ) use (
                $shortcodeTemplate,
                $format,
                $page
            ) {
                return $shortcodeTemplate->renderSimple($format, $page, ['shortcode' => $shortcode]);
            };
        }
        $environment = new CommonMarkEnvironment([
            'shortcodes' => ['shortcodes' => $shortcodes],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addExtension(new ShortcodeExtension());
        return $environment;
    }

    private function getCachePool(string $subdir): CacheItemPoolInterface
    {
        return new FilesystemAdapter($subdir, 0, $this->site->getDir() . '/cache/');
    }
}
