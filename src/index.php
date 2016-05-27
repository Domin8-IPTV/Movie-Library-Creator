<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/TheMovieDb.php';

define('IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/');

// Helper functions
function ends_with($str, $separator = '/') {
    if (substr($str, -1) !== $separator)
        $str .= $separator;

    return $str;
}

function is_video_mime_type($mime) {
    return substr($mime, 0, strpos($mime, '/')) === 'video';
}

function search_for_movie($name, $hash, $themoviedb, $climate, &$cache) {
    $searchResults = $themoviedb->searchMovie($name);

    if (count($searchResults) === 1) {
        $climate->whisper(sprintf('Found %s (%s)', $searchResults[0]->title, $searchResults[0]->release_date));

        $meta = $searchResults[0];
        $cache[$hash] = $meta;

        return $meta;
    }

    if (count($searchResults) > 1) {
        $options = ['Skip'];
        $responses = [];
        foreach ($searchResults as $result) {
            $title = sprintf('%s (%s)', $result->title, $result->release_date);
            $options[] = $title;
            $responses[$title] = $result;
        }

        $climate->comment(sprintf('Multiple results were found for "%s":', $name));
        $input = $climate->radio('Please choose:', $options);

        $response = $input->prompt();
        if ($response !== 'Skip') {
            $meta = $responses[$response];
            $cache[$hash] = $meta;
            return $meta;
        }

        return null;
    }

    $climate->error(sprintf('Could not find results for "%s"!', $name));
    return null;
}

function get_movie_details($id, $hash, $themoviedb, $climate, &$cache) {
    $meta = $themoviedb->getMovie($name);

    if ($meta === null) {
        $climate->error(sprintf('Could not find results for "%d"!', $id));
        return null;
    }

    $cache[$hash] = $meta;

    return $meta;
}

// Define cli options
$climate = new League\CLImate\CLImate;

$climate->description('Creates a json file from your movie library.');
$climate->arguments->add([
    'movies' => [
        'prefix'       => 'm',
        'longPrefix'   => 'movies',
        'description'  => 'Directory where the movies are',
        'castTo'       => 'string',
        'required'     => true
    ],
    'key' => [
        'prefix'       => 'k',
        'longPrefix'   => 'key',
        'description'  => 'TheMovieDB API key',
        'castTo'       => 'string',
        'required'     => true
    ],
    'cache' => [
        'prefix'       => 'c',
        'longPrefix'   => 'cache',
        'description'  => 'Cache JSON file',
        'defaultValue' => '../cache.json',
        'castTo'       => 'string'
    ],
    'language' => [
        'prefix'       => 'l',
        'longPrefix'   => 'language',
        'description'  => 'Default language',
        'defaultValue' => 'en',
        'castTo'       => 'string'
    ],
    'name' => [
        'prefix'       => 'n',
        'longPrefix'   => 'name',
        'description'  => 'Name of the website generated',
        'defaultValue' => 'My Movie Library',
        'castTo'       => 'string'
    ],
    'force' => [
        'prefix'       => 'f',
        'longPrefix'   => 'force',
        'description'  => 'Use TheMovieDB cache but only the ID',
        'defaultValue' => false,
        'noValue'      => true
    ],
]);

// Parse cli options
try {
    $climate->arguments->parse();
} catch (\Exception $e) {
    $climate->error('Invalid command line options!');
    $climate->usage();

    exit(1);
}

// Movie path
$dir = $climate->arguments->get('movies');
$dir = ends_with($dir);
if (!is_dir($dir) || !is_readable($dir)) {
    $climate->error(sprintf('Directory does not exist or is not readable: %s', $dir));
    exit(1);
}

// TheMovieDB API key
$key = $climate->arguments->get('key');
if (empty($key)) {
    $climate->error('Please specify an api key!');
    exit(1);
}

$themoviedb = new VisualAppeal\Movies\TheMovieDb($key, $climate->arguments->get('language'));

// Cache file
$cacheFilename = $climate->arguments->get('cache');
if (!file_exists($cacheFilename)) {
    $climate->comment(sprintf('Creating cache file %s...', $cacheFilename));
    if (!file_put_contents($cacheFilename, '[]')) {
        $climate->error(sprintf('Could not create cache file: %s', $cacheFilename));
        exit(1);
    }
}

if (!is_writable($cacheFilename)) {
    $climate->error(sprintf('Cache file is not writeable: %s', $cacheFilename));
    exit(1);
}

$cache = (array) json_decode(file_get_contents($cacheFilename));
if ($cache === false) {
    $climate->error(sprintf('Could not read json from cache file: %s', $cacheFilename));
    exit(1);
}

// Force option
$force = (bool) $climate->arguments->get('force');

// Get files from directory
$adapter = new League\Flysystem\Adapter\Local($dir);
$filesystem = new League\Flysystem\Filesystem($adapter);

$contents = $filesystem->listContents(null, true);
$movies = [];

$finfo = finfo_open(FILEINFO_MIME_TYPE);

try {
    $ffprobe = FFMpeg\FFProbe::create();
} catch (\Exception $e) {
    $climate->error($e->getMessage());
    exit(1);
}

foreach ($contents as $object) {
    $path = $dir . $object['path'];
    if (is_dir($path))
        continue;

    $mime = finfo_file($finfo, $path);

    if (!is_video_mime_type($mime))
        continue;

    $size = filesize($path);
    if ($size < 1024 * 1024 * 50) { // 50 MB

        continue;
    }

    $name = pathinfo($path, PATHINFO_FILENAME);
    $hash = sha1($path);

    $height = 0;
    $width = 0;

    // Extract video information
    $videoInformation = [];
    $videos = $ffprobe
        ->streams($path)
        ->videos();
    foreach ($videos as $video) {
        $videoInformation[] = [
            'codec_name' => $video->get('codec_name'),
            'frame_rate' => $video->get('avg_frame_rate')
        ];

        $width = ($video->get('width') !== 0) ? $video->get('width') : $width;
        $height = ($video->get('height') !== 0) ? $video->get('height') : $height;
    }

    // Extract audio information
    $audioInformation = [];
    $audios = $ffprobe
        ->streams($path)
        ->audios();
    foreach ($audios as $audio) {
        $tags = $audio->get('tags');

        $audioInformation[] = [
            'codec_name' => $audio->get('codec_name'),
            'sample_rate' => $audio->get('sample_rate'),
            'language' => isset($tags['language']) ? $tags['language'] : null
        ];
    }

    // Extract file information
    $probe = $ffprobe
        ->format($path);

    $duration = (float) $probe->get('duration');
    $bitrate =  (int) $probe->get('bit_rate');
    $size = (int) $probe->get('size');

    // Get information from TheMovieDB
    $meta = null;

    if (!isset($cache[$hash])) {
        $originalName = $name;
        $meta = search_for_movie($name, $hash, $themoviedb, $climate, $cache);

        if ($meta === null) {
            $name = preg_replace('/\d+\s*\-\s*(.*)/', '${1}', $name);
            if ($name !== $originalName) {
                $meta = search_for_movie($name, $hash, $themoviedb, $climate, $cache);
                $originalName = $name;
            }
        }

        if ($meta === null) {
            $name = str_replace('-', ' ', $name);
            while (strpos($name, '  ') !== false) {
                $name = str_replace('  ', ' ', $name);
            }

            if ($name !== $originalName) {
                $meta = search_for_movie($name, $hash, $themoviedb, $climate, $cache);
                $originalName = $name;
            }
        }
    } else {
        if ($force)
            $meta = get_movie_details($meta['id'], $hash, $themoviedb, $climate, $cache);
        else
            $meta = $cache[$hash];
    }

    // Save data
    $movies[$name] = [
        'path' => $path,
        'meta' => $meta,
        'video' => $videoInformation,
        'audio' => $audioInformation,
        'duration' => $duration,
        'bitrate' => $bitrate,
        'width' => $width,
        'height' => $height,
        'size' => $size
    ];

    // Save themoviedb cache
    file_put_contents($cacheFilename, json_encode($cache));
}

$data = [
    'movies' => $movies
];

$output = file_get_contents(__DIR__ . '/../public/index.template');
$output = str_replace('_DATA_', json_encode($data), $output);
$output = str_replace('_NAME_', htmlentities(strip_tags($climate->arguments->get('name'))), $output);
$output = str_replace('_IMAGE_BASE_URL_', IMAGE_BASE_URL, $output);

file_put_contents(__DIR__ . '/../public/index.html', $output);
