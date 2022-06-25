<?php

declare(strict_types=1);

namespace App;

use App\Command\CommandBase;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class Page
{
    /** @var Site */
    protected $site;

    /** @var string */
    protected $id;

    /** @var ?string */
    protected $contents;

    public function __construct(Site $site, string $id)
    {
        $this->site = $site;
        $this->id = '/' . ltrim($id, '/');
    }

    /**
     * Get the Site to which this Page belongs.
     */
    public function getSite(): Site
    {
        return $this->site;
    }

    /**
     * Page ID. Always starts with a slash.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get a relative link from this page to another.
     *
     * @param string $targetId The ID of the target page.
     */
    public function getLink(string $targetId): string
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
     * Get the template's name, without format name or twig file extension.
     */
    public function getTemplateName(): string
    {
        // Cast to string in order to handle numeric template names such as '404'.
        return (string) $this->getMetadata()['template'];
    }

    /**
     * The 'contents' is the full original file, both metadata and body.
     */
    public function getContents(): string
    {
        if ($this->contents !== null) {
            return $this->contents;
        }
        if (!file_exists($this->getFilename())) {
            return '';
        }
        $this->contents = file_get_contents($this->getFilename());
        return $this->contents;
    }

    /**
     * Get the full filesystem path to the source Markdown file for this page.
     */
    public function getFilename(): string
    {
        return $this->site->getDir() . '/content' . $this->getId() . $this->site->getExt();
    }

    /**
     * @return mixed[] With 'metadata' and 'body' keys.
     */
    private function parseContents(): array
    {
        $contents = $this->getContents();
        $defaultMetadata = ['template' => 'index'];
        preg_match('/^(---+)/', $contents, $hyphenMatches);
        if (isset($hyphenMatches[1])) {
            $hyphenCount = strlen($hyphenMatches[1]);
            $frontmatterClosePos = strpos($contents, $hyphenMatches[1], $hyphenCount);
            $frontmatterData = trim(substr($contents, $hyphenCount, $frontmatterClosePos - $hyphenCount));
            try {
                $parsedMetadata = Yaml::parse($frontmatterData, Yaml::PARSE_DATETIME);
            } catch (Throwable $throwable) {
                CommandBase::writeln(
                    'Error reading metadata from ' . $this->getId() . "\n> " . $throwable->getMessage()
                );
                $parsedMetadata = $defaultMetadata;
            }
            $metadata = array_merge($defaultMetadata, $parsedMetadata);
            $body = substr($contents, $frontmatterClosePos + $hyphenCount);
        } else {
            $metadata = $defaultMetadata;
            $body = $contents;
        }
        return [
            'metadata' => $metadata,
            'body' => trim($body),
        ];
    }

    /**
     * Get a file's metadata.
     *
     * @return string[]
     */
    public function getMetadata(): array
    {
        return $this->parseContents()['metadata'];
    }

    public function getBody(): string
    {
        return $this->parseContents()['body'];
    }

    /**
     * Write new content to this page's file.
     *
     * @param mixed[] $newMetadata
     */
    public function write(array $newMetadata, string $newBody): void
    {
        // Loose equality check.
        $trimmedBody = trim($newBody);
        if ($newMetadata == $this->getMetadata() && $trimmedBody == $this->getBody()) {
            CommandBase::writeln('No change required');
            return;
        }

        $yaml = Yaml::dump($newMetadata, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
        Util::mkdir(dirname($this->getFilename()));
        $body = $trimmedBody ? "$trimmedBody\n" : '';
        file_put_contents($this->getFilename(), "---\n$yaml---\n$body");
        // Reset contents to ensure it'll be re-read when next accessed.
        $this->contents = null;
    }
}
