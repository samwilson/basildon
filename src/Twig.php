<?php

declare(strict_types=1);

namespace App;

use App\Markdown\MarkdownToHtml;
use App\Markdown\MarkdownToLatex;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Mediawiki\Api\FluentRequest;
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
            new TwigFunction('strtotime', 'strtotime'),
            new TwigFunction('tex_url', [$this, 'functionTexUrl']),
            new TwigFunction('wikidata', [$this, 'functionWikidata']),
            new TwigFunction('commons', [$this, 'functionCommons']),
            new TwigFunction('flickr', [$this, 'functionFlickr']),
        ];
    }

    public function filterMarkdownToHtml(string $input): string
    {
        $markdown = new MarkdownToHtml;
        $markdown->setSite($this->site);
        $markdown->setPage($this->page);
        return $markdown->parse($input);
    }

    public function filterMarkdownToLatex(string $input): string
    {
        $markdown = new MarkdownToLatex;
        $markdown->setSite($this->site);
        $markdown->setPage($this->page);
        return $markdown->parse($input);
    }

    /**
     * @param mixed $a
     * @return bool
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
        Util::mkdir(dirname($outputFilepath));

        // Download to a local directory if it's not already there.
        if (!file_exists($outputFilepath)) {
            (new Client)->get($url, [RequestOptions::SINK => fopen($outputFilepath, 'w+')]);
        }

        // Return the full path to the downloaded file..
        return $outputFilepath;
    }

    /**
     * @return mixed[]
     */
    public function functionWikidata(string $wikidataId): array
    {
        $api = $this->site->getMediawikiApi('https://www.wikidata.org/w/api.php');
        $request = FluentRequest::factory()
            ->setAction('wbgetentities')
            ->setParam('ids', $wikidataId);
        $result = $api->getRequest($request);
        return $result['entities'][$wikidataId];
    }

    /**
     * @return mixed[]
     */
    public function functionCommons(string $filename): array
    {
        $api = $this->site->getMediawikiApi('https://commons.wikimedia.org/w/api.php');
        $fileInfoResponse = $api->getRequest(FluentRequest::factory()
            ->setAction('query')
            ->addParams([
                'prop' => 'imageinfo',
                'iiprop' => 'url',
                'iiurlwidth' => $this->site->getConfig()->embedWidth ?? 800,
                'titles' => 'File:' . $filename,
            ]));
        $fileInfo = array_shift($fileInfoResponse['query']['pages']);
        $mediaInfoResponse = $api->getRequest(FluentRequest::factory()
            ->setAction('wbgetentities')
            ->addParams(['ids' => 'M' . $fileInfo['pageid']]));
        $mediaInfo = array_shift($mediaInfoResponse['entities']);
        return array_merge($fileInfo, $mediaInfo);
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
}
