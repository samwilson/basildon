<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use SimpleXMLElement;

final class WikidataQuery
{
    protected string $query;
    private Client $client;
    private string $queryService;

    /**
     * WikidataQuery constructor.
     *
     * @param string $query The Sparql query to execute.
     * @param Client $client HTTP client.
     * @param string $queryService The domain name of the Wikidata Query Service to use (without protocol).
     */
    public function __construct($query, Client $client, string $queryService)
    {
        $this->query = $query;
        $this->client = $client;
        $this->queryService = $queryService;
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
        $url = 'https://' . $this->queryService . '/bigdata/namespace/wdq/sparql?query=' . urlencode($query);
        $response = $this->client->request('GET', $url);
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
