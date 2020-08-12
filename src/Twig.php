<?php

declare(strict_types=1);

namespace App;

use App\Markdown\MarkdownToHtml;
use App\Markdown\MarkdownToLatex;
use DateTime;
use DateTimeZone;
use Endroid\QrCode\QrCode;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Mediawiki\Api\FluentRequest;
use Samwilson\PhpFlickr\PhotosApi;
use Samwilson\PhpFlickr\PhpFlickr;
use Stash\Driver\FileSystem;
use Stash\Pool;
use Throwable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Twig extends AbstractExtension
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
            new TwigFunction('flickr', [$this, 'functionFlickr']),
            new TwigFunction('qrcode', [$this, 'functionQrCode']),
        ];
    }

    public function filterMarkdownToHtml(string $input): string
    {
        $markdown = new MarkdownToHtml();
        $markdown->setSite($this->site);
        $markdown->setPage($this->page);
        return $markdown->parse($input);
    }

    public function filterMarkdownToLatex(string $input): string
    {
        $markdown = new MarkdownToLatex();
        $markdown->setSite($this->site);
        $markdown->setPage($this->page);
        return $markdown->parse($input);
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
        Build::writeln('TeX file download: ' . basename($url));

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
        if (isset(static::$data['wikidata'][$wikidataId])) {
            return static::$data['wikidata'][$wikidataId];
        }
        $api = $this->site->getMediawikiApi('https://www.wikidata.org/w/api.php');
        $request = FluentRequest::factory()
            ->setAction('wbgetentities')
            ->setParam('ids', $wikidataId);
        Build::writeln('Wikidata fetch info: ' . $wikidataId);
        $result = $api->getRequest($request);
        static::$data['wikidata'][$wikidataId] = $result['entities'][$wikidataId];
        return static::$data['wikidata'][$wikidataId];
    }

    /**
     * @return string[]
     */
    public function functionFlickr(string $photoId): array
    {
        if (isset(static::$data['flickr'][$photoId])) {
            return static::$data['flickr'][$photoId];
        }
        $config = $this->site->getConfig()->flickr;
        $flickr = new PhpFlickr($config->api_key, $config->api_secret);
        $pool = new Pool(new FileSystem(['path' => $this->site->getDir() . '/cache/flickr']));
        $flickr->setCache($pool);
        $shortUrl = $flickr->urls()->getShortUrl($photoId);
        Build::writeln("Flickr fetch info: $photoId $shortUrl");
        $info = $flickr->photos()->getInfo($photoId);
        static::$data['flickr'][$photoId] = [
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
        return static::$data['flickr'][$photoId];
    }

    /**
     * @return mixed[]
     */
    public function functionCommons(string $filename): array
    {
        if (isset(static::$data['commons'][$filename])) {
            return static::$data['commons'][$filename];
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
        Build::writeln("Commons fetch info: $filename");
        $mediaInfoResponse = $api->getRequest(FluentRequest::factory()
            ->setAction('wbgetentities')
            ->addParams(['ids' => 'M' . $fileInfo['pageid']]));
        $mediaInfo = array_shift($mediaInfoResponse['entities']);
        static::$data['commons'][$filename] = array_merge($fileInfo, $mediaInfo);
        return static::$data['commons'][$filename];
    }

    /**
     * @return string Relative URL string, of the form '/assets/qrcodes/hash.svg'.
     */
    public function functionQrCode(string $text): string
    {
        $qr = new QrCode($text);
        $qr->setWriterByName('svg');
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
        $qr->writeFile($cachePath);
        // Copy it to output.
        copy($cachePath, $filePath);
        return $assetPath;
    }

    public function escapeTex(Environment $env, ?string $string = '', string $charset = 'utf-8'): string
    {
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
        $out = str_replace('"', '""', $string);
        if (strpos($out, '"') !== false || strpos($out, ',') !== false) {
            $out = '"' . $out . '"';
        }
        return $out;
    }
}
