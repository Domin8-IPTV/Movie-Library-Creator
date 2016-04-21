<?php namespace VisualAppeal\Movies;

use GuzzleHttp\Client;

class TheMovieDb
{
    /**
     * HTTP client
     *
     * @var GuzzleHttp\Client
     */
    protected $client;

    /**
     * TheMovieDB Api Key
     *
     * @var string
     */
    protected $key;

    /**
     * TheMovieDB Api language
     *
     * @var string
     */
    protected $language;

    public function __construct($key, $language = 'en', $baseUrl = 'https://api.themoviedb.org/3/')
    {
        $this->key = $key;
        $this->language = $language;

        $this->client = new Client([
            'base_uri' => $baseUrl,
        ]);
    }

    public function searchMovie($query)
    {
        $response = $this->client->get('search/movie', [
            'query' => [
                'api_key' => $this->key,
                'query' => $query,
                'language' => $this->language
            ]
        ]);

        if (!$response->getStatusCode() === 200)
            throw new \Exception('Invalid status code: ' . $response->getStatusCode());

        $json = json_decode($response->getBody());
        if ($json === null)
            throw new \Exception('Could not parse json: ' . $response->getBody());

        return $json->results;
    }

    public function getMovie($id)
    {
        $response = $this->client->get(sprintf('movie/%d', $id), [
            'query' => [
                'api_key' => $this->key,
                'language' => $this->language
            ]
        ]);

        if (!$response->getStatusCode() === 200)
            throw new \Exception('Invalid status code: ' . $response->getStatusCode());

        $json = json_decode($response->getBody());
        if ($json === null)
            throw new \Exception('Could not parse json: ' . $response->getBody());

        return $json;
    }
}
