<?php

declare(strict_types=1);

namespace App;

use App\Command\CommandBase;
use DateTime;
use DateTimeZone;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use League\CommonMark\Environment\Environment as CommonMarkEnvironment;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\MarkdownConverter;
use Mediawiki\Api\FluentRequest;
use Samwilson\CommonMarkLatex\LatexRendererExtension;
use Samwilson\CommonMarkShortcodes\Shortcode;
use Samwilson\CommonMarkShortcodes\ShortcodeExtension;
use Samwilson\PhpFlickr\PhotosApi;
use Samwilson\PhpFlickr\PhpFlickr;
use Stash\Driver\FileSystem;
use Stash\Pool;
use Throwable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class Twig extends AbstractExtension
{
    /** @var Site */
    protected $site;

    /** @var Page */
    protected $page;

    /** @var mixed[] Runtime cache of API-retrieved info. */
    protected static $data = [
        'commons' => [],
        'wikidata' => [],
        'flickr' => [],
    ];

    public function __construct(Site $site, Page $page)
    {
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
            new TwigFunction('tex_url', [$this, 'functionTexUrl']),
            new TwigFunction('wikidata', [$this, 'functionWikidata']),
            new TwigFunction('commons', [$this, 'functionCommons']),
            new TwigFunction('wikipedia', [$this, 'functionWikipedia']),
            new TwigFunction('wikidata_query', [$this, 'functionWikidataQuery']),
            new TwigFunction('flickr', [$this, 'functionFlickr']),
            new TwigFunction('qrcode', [$this, 'functionQrCode']),
        ];
    }

    public function filterMarkdownToHtml(string $input): string
    {
        $environment = $this->getCommonMarkEnvironment('html');
        $environment->addExtension(new AutolinkExtension());
        $environment->addEventListener(DocumentPreRenderEvent::class, function (DocumentPreRenderEvent $event): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if (!$node instanceof Image) {
                    continue;
                }
                if (substr($node->getUrl(), 0, 4) !== 'http') {
                    $node->setUrl($this->page->getLink($node->getUrl()));
                }
            }
        });
        $converter = new MarkdownConverter($environment);
        return $converter->convert($input)->getContent();
    }

    public function filterMarkdownToLatex(string $input): string
    {
        $environment = $this->getCommonMarkEnvironment('tex');
        $environment->addExtension(new LatexRendererExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addEventListener(DocumentPreRenderEvent::class, function (DocumentPreRenderEvent $event): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if (!$node instanceof Image) {
                    continue;
                }
                if (substr($node->getUrl(), 0, 4) === 'http') {
                    // Absolute URLs.
                    $node->setUrl($this->functionTexUrl($node->getUrl()));
                } else {
                    // Relative URLs.
                    $link = $this->site->getDir() . '/content' . $node->getUrl();
                    if (!is_file($link)) {
                        throw new Exception("Unable to find image: $link");
                    }
                    $node->setUrl($link);
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
        CommandBase::writeln('TeX file download: ' . basename($url));

        // Set up file and directory names.
        $filename = md5($url) . '.' . pathinfo($url, PATHINFO_EXTENSION);
        $outputFilepath = $this->site->getDir() . '/cache/tex/_urls/' . $filename;

        // Check cache and return if the file exists and was modified within the permitted time-frame.
        $minCacheTime = time() - $this->site->getTtl();
        if (file_exists($outputFilepath) && filemtime($outputFilepath) >= $minCacheTime) {
            return $outputFilepath;
        }

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

        // Return the full path to the downloaded file..
        return $outputFilepath;
    }

    /**
     * @return mixed[]
     */
    public function functionWikidata(string $wikidataId): array
    {
        if (isset(self::$data['wikidata'][$wikidataId])) {
            return self::$data['wikidata'][$wikidataId];
        }
        $api = $this->site->getMediawikiApi('https://www.wikidata.org/w/api.php');
        $request = FluentRequest::factory()
            ->setAction('wbgetentities')
            ->setParam('ids', $wikidataId);
        CommandBase::writeln('Wikidata fetch info: ' . $wikidataId);
        $result = $api->getRequest($request);
        self::$data['wikidata'][$wikidataId] = $result['entities'][$wikidataId];
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
     * @return string[]
     */
    public function functionFlickr(string $photoId): array
    {
        if (isset(self::$data['flickr'][$photoId])) {
            return self::$data['flickr'][$photoId];
        }
        $config = $this->site->getConfig()->flickr;
        $flickr = new PhpFlickr($config->api_key, $config->api_secret);
        $pool = new Pool(new FileSystem(['path' => $this->site->getDir() . '/cache/flickr']));
        $flickr->setCache($pool);
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
        $api = $this->site->getMediawikiApi('https://commons.wikimedia.org/w/api.php');
        $fileInfoResponse = $api->getRequest(FluentRequest::factory()
            ->setAction('query')
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
        $mediaInfoResponse = $api->getRequest(FluentRequest::factory()
            ->setAction('wbgetentities')
            ->addParams(['ids' => 'M' . $fileInfo['pageid']]));
        $mediaInfo = array_shift($mediaInfoResponse['entities']);
        self::$data['commons'][$filename] = array_merge($fileInfo, $mediaInfo);
        return self::$data['commons'][$filename];
    }

    public function functionWikipedia(string $lang, string $articleTitle): string
    {
        if (isset(self::$data['wikipedia'][$articleTitle])) {
            return self::$data['wikipedia'][$articleTitle];
        }
        CommandBase::writeln("Wikipedia fetch extract: $articleTitle");
        $url = "https://$lang.wikipedia.org/api/rest_v1/page/summary/" . str_replace(' ', '_', $articleTitle);
        $json = (new Client())->get($url)->getBody()->getContents();
        $response = json_decode($json, true);
        if (!$response) {
            throw new Exception("Unable to get extract of Wikipedia article: $articleTitle");
        }
        self::$data['commons'][$articleTitle] = $response['extract_html'];
        return self::$data['commons'][$articleTitle];
    }

    /**
     * @return string Relative URL string, of the form '/assets/qrcodes/hash.svg'.
     */
    public function functionQrCode(string $text): string
    {
        $qrFilename = md5($text) . '.svg';
        $assetPath = '/assets/qrcodes/' . $qrFilename;
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
     * @param Environment $env The Twig environment. Not used.
     * @param string|null $string The string to escape.
     * @param string $charset The charset of the string. Not used.
     */
    public function escapeTex(Environment $env, ?string $string = '', string $charset = 'utf-8'): string
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
     * @param Environment $env The Twig environment.
     * @param string|null $string The string to escape.
     * @param string $charset The charset of the string.
     */
    public function escapeCsv(Environment $env, ?string $string = '', string $charset = 'utf-8'): string
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
        foreach ($this->site->getTemplates('shortcodes') as $shortcodeTemplate) {
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
}
