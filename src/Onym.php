<?php

namespace Blaspsoft\Onym;

use DateTime;
use Illuminate\Support\Str;

/**
 * @method static string make(string $defaultFilename, ?string $strategy = null, ?string $extension = null, ?array $options = null)
 * @method static string random(string $extension, ?array $options = [])
 * @method static string uuid(string $extension, ?array $options = [])
 * @method static string timestamp(string $defaultFilename, string $extension, ?array $options = [])
 * @method static string date(string $defaultFilename, string $extension, ?array $options = [])
 * @method static string numbered(string $defaultFilename, string $extension, ?array $options = [])
 * @method static string slug(string $defaultFilename, string $extension, ?array $options = [])
 * @method static string hash(string $defaultFilename, string $extension, ?array $options = [])
 */
class Onym
{
    /**
     * The strategy to use.
     *
     * @var string
     */
    public string $strategy;

    /**
     * The options to use.
     *
     * @var array
     */
    public array $options;

    /**
     * The default filename to use.
     *
     * @var string
     */
    public string $defaultFilename;

    /**
     * The default extension to use.
     *
     * @var string
     */
    public string $defaultExtension;

    public function __construct()
    {
        $this->strategy = config('onym.strategy', 'random');
        $this->options = config('onym.options', []);
        $this->defaultFilename = config('onym.default_filename', 'file');
        $this->defaultExtension = config('onym.default_extension', 'txt');
    }

    /**
     * Generate a new filename based on the strategy and options.
     *
     * @param string|null $defaultFilename The original filename.
     * @param string|null $extension The extension of the file.
     * @param string|null $strategy The strategy to use (falls back to config value if null).
     * @param array|null $options The options to use (merges with config options if provided).
     * @return string The new filename.
     */
    public function make(
        ?string $defaultFilename = null, 
        ?string $strategy = null,
        ?string $extension = null,  
        ?array $options = null
    )
    {
        $useStrategy = $strategy ?? $this->strategy;
        $useOptions = $options !== null ? $this->mergeOptions($options, $useStrategy, $this->options) : $this->options[$useStrategy] ?? [];
        $defaultFilename = $defaultFilename ?? $this->defaultFilename;
        $extension = $extension ?? $this->defaultExtension;

        return match ($useStrategy) {
            'random' => $this->random($extension, $useOptions),
            
            'uuid' => $this->uuid($extension, $useOptions),
            
            'timestamp' => $this->timestamp($defaultFilename, $extension, $useOptions),
            
            'date' => $this->date($defaultFilename, $extension, $useOptions),
            
            'numbered' => $this->numbered($defaultFilename, $extension, $useOptions),
            
            'slug' => $this->slug($defaultFilename, $extension),
            
            'hash' => $this->hash($defaultFilename, $extension, $useOptions),
            
            default => $defaultFilename . '.' . $extension,
        };
    }

    /**
     * Generate a random string of characters.
     *
     * @param int|null $length The length of the random string.
     * @param string|null $extension The extension of the file.
     * @return string The random string.
     */
    public function random(string $extension, ?array $options = [])
    {
        $options = $this->mergeOptions($options, 'random', $this->options);
        $length = $options['length'] ?? 16;
        $filename = Str::random($length);
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Generate a UUID string.
     *
     * @param string|null $extension The extension of the file.
     * @return string The UUID string.
     */
    public function uuid(string $extension, ?array $options = [])
    {
        $options = $this->mergeOptions($options, 'uuid', $this->options);
        $filename = (string) Str::uuid();
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Generate a timestamp string.
     *
     * @param string|null $defaultFilename The original filename.
     * @param string|null $extension The extension of the file.
     * @param array $options The options array.
     * @return string The timestamp string.
     */
    public function timestamp(string $defaultFilename, string $extension, ?array $options = [])
    {   
        $options = $this->mergeOptions($options, 'timestamp', $this->options);

        $format = $options['format'] ?? 'Y-m-d_H-i-s';
        $date = new DateTime();
        $filename = $date->format($format) . '_' . $defaultFilename;
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Generate a date string.
     *
     * @param string|null $defaultFilename The original filename.
     * @param string|null $extension The extension of the file.
     * @param array $options The options array. 
     * @return string The date string.
     */
    public function date(string $defaultFilename, string $extension, ?array $options = [])
    {
        $options = $this->mergeOptions($options, 'date', $this->options);
        $format = $options['format'] ?? 'Y-m-d';
        $date = new DateTime();
        $filename = $date->format($format) . '_' . $defaultFilename;
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Generate a numbered string.
     *
     * @param string|null $defaultFilename The original filename.
     * @param string|null $extension The extension of the file.
     * @param array $options The options array.
     * @return string The numbered string.
     */
    public function numbered(string $defaultFilename, string $extension, ?array $options = [])
    {
        $options = $this->mergeOptions($options, 'numbered', $this->options);
        $number = $options['number'] ?? 1;
        $filename = $defaultFilename . '_' . $number;
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Generate a slug string.
     *
     * @param string|null $defaultFilename The original filename.
     * @param string|null $extension The extension of the file.
     * @return string The slug string.
     */
    public function slug(string $defaultFilename, string $extension, ?array $options = [])
    {
        $options = $this->mergeOptions($options, 'slug', $this->options);
        $filename = Str::slug($defaultFilename, $options['separator']);
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Generate a hash string.
     *
     * @param string|null $defaultFilename The original filename.
     * @param string|null $extension The extension of the file.
     * @param array $options The options array.
     * @return string The hash string.
     */
    public function hash(string $defaultFilename, string $extension, ?array $options = [])
    {
        $options = $this->mergeOptions($options, 'hash', $this->options);
        $algorithm = $options['algorithm'] ?? 'md5';

        if (!in_array($algorithm, hash_algos())) {
            throw new \InvalidArgumentException("Invalid hash algorithm: {$algorithm}");
        }

        $hash = hash($algorithm, $defaultFilename);
        $filename = $hash;
        return $this->applyAffixes($filename, $extension, $options);
    }

    /**
     * Merge options with the default options.
     *
     * @param array $options The options to merge.
     * @param string $strategy The strategy being used.
     * @param array $defaultOptions The default options.
     * @return array The merged options.
     */
    private function mergeOptions(array $options, string $strategy, array $defaultOptions): array
    {
        $strategyOptions = $defaultOptions[$strategy] ?? [];
        return array_merge($strategyOptions, $options);
    }

    /**
     * Apply prefix and suffix to filename
     *
     * @param string $filename
     * @param array $options
     * @return string
     */
    private function applyAffixes(string $filename, string $extension, ?array $options = []): string
    {
        if (isset($options['prefix'])) {
            $filename = $options['prefix'] . $filename;
        }
        if (isset($options['suffix'])) {
            $filename = $filename . $options['suffix'];
        }
        return $filename . '.' . $extension;
    }
}
