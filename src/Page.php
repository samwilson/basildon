<?php

namespace App;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Page
{

    /** @var Site */
    protected $site;

    /** @var string */
    protected $id;

    /** @var string */
    protected $contents;

    public function __construct(Site $site, string $id)
    {
        $this->site = $site;
        $this->id = '/' . ltrim($id, '/');
    }

    /**
     * Get the Site to which this Page belongs.
     * @return Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Page ID. Always starts with a slash.
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get a relative link from this page to another.
     * @param string $targetId The ID of the target page.
     * @return string
     */
    public function getLink(string $targetId)
    {
        // Make sure the target starts with a slash.
        if (substr($targetId, 0, 1) !== '/') {
            $targetId = "/$targetId";
        }

        // Split the paths into their constituent parts.
        $partsTarget = array_values(array_filter(explode('/', $targetId)));
        $partsThis = array_values(array_filter(explode('/', $this->getId())));

        $out = [];

        // Navigate back to the root.
        for ($i = 1; $i < count($partsThis); $i++) {
            $out[] = '..';
        }
        // Append target path.
        $out = array_merge($out, $partsTarget);

        return join('/', $out);
    }

    /**
     * @return string[]
     */
//  public function getFormats() {
//      $finder = new Finder();
//      $finder->files()
//          ->in($this->site->getDir().'/templates')
//          ->name( $this->getTemplate().'*.twig' );
//      $formats = [];
//      foreach ( $finder as $file ) {
//          preg_match('/^.*\.(.*)\.twig$/', $file->getFilename(), $matches);
//          $formats[] = $matches[1];
//      }
//      return $formats;
//  }

    /**
     * Get the template's name, without format name or twig file extension.
     * @return string
     */
    public function getTemplateName()
    {
//      $metadata = $this->getMetadata();
//      $template = new Template($this->getSite(), $metadata['template']);
//      return $template;
        return $this->getMetadata()['template'];
        //return pathinfo($this->getTemplate(), PATHINFO_EXTENSION);
    }

//  public function render() {
//      $renderedTemplate = $twig->render($page->getTemplate().".$format.twig", [
//          'database' => $db,
//          'site' => $site,
//          'page' => $page,
//      ]);
//      $outFile = $dir.'/output/'.$page->getId().'.'.$format;
//      if (!is_dir(dirname($outFile))) {
//          mkdir(dirname($outFile), 0755, true);
//      }
//      file_put_contents($outFile, $renderedTemplate);
//
//      // Post-process tex files to PDF.
//      if ($format === 'tex') {
//          $process = new Process( [  ] );
//      }
//  }

    protected function getContents()
    {
        if ($this->contents) {
            return $this->contents;
        }
        $this->contents = file_get_contents($this->site->getDir() . '/content' . $this->getId() . '.txt');
        return $this->contents;
    }

    /**
     * Get a file's metadata.
     * @return string[]
     */
    public function getMetadata()
    {
        $contents = $this->getContents();
        preg_match("/---\n(.*)\n---/ms", $contents, $matches);
        $metadata = [];
        if (!isset($matches[1])) {
            return $metadata;
        }
        foreach (explode("\n", $matches[1]) as $line) {
            $datum = array_map('trim', array_filter(explode(':', $line)));
            $metadata[$datum[0]] = $datum[1];
        }
        if (!isset($metadata['template'])) {
            $metadata['template'] = 'index';
        }
        return $metadata;
    }

    public function getBody()
    {
        $contents = $this->getContents();
        preg_match("/---\n.*\n---\n(.*)/ms", $contents, $matches);
        return isset($matches[1]) ? trim($matches[1]) : '';
    }
}
