<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use SimpleXMLElement;

class WikidataQuery
{
    /** @var string The Sparql query to run. */
    protected $query;

    /**
     * WikidataQuery constructor.
     *
     * @param string $query The Sparql query to execute.
     */
    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * Get the results of this query.
     *
     * @return string[][] Array of results keyed by the names given in the Sparql query.
     */
    public function fetch(): array
    {
        $out = [];
        $result = $this->getXml($this->query);
        foreach ($result->results->result as $res) {
            $out[] = $this->getBindings($res);
        }
        return $out;
    }

    /**
     * Get the XML result of a Sparql query.
     *
     * @param string $query The Sparql query to execute.
     */
    protected function getXml(string $query): SimpleXMLElement
    {
        $url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=' . urlencode($query);
        $client = new Client();
        $response = $client->request('GET', $url);
        return new SimpleXMLElement($response->getBody()->getContents());
    }

    /**
     * Restructure the XML that comes back from the Wikidata Query Service
     *
     * @param SimpleXMLElement $xml The XML for one result item.
     * @return string[]
     */
    protected function getBindings(SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->binding as $binding) {
            assert($binding instanceof SimpleXMLElement);
            if (isset($binding->literal)) {
                $out[(string) $binding['name']] = (string) $binding->literal;
            }
            if (isset($binding->uri)) {
                $out[(string) $binding['name']] = (string) $binding->uri;
            }
        }
        return $out;
    }
}
