<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;

use function array_map;
use function json_decode;
use function urlencode;

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
    public function __construct(string $query, Client $client, string $queryService)
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
        $query = urlencode($this->query);
        $url = 'https://' . $this->queryService . '/bigdata/namespace/wdq/sparql?format=json&query=' . $query;
        $response = $this->client->request('GET', $url);
        $json = json_decode($response->getBody()->getContents() ?? '', true);
        $out = [];
        foreach ($json['results']['bindings'] ?? [] as $data) {
            $out[] = array_map(static fn ($datum) => $datum['value'], $data);
        }

        return $out;
    }
}
