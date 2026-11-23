<?php

namespace dokuwiki\plugin\h5p;

/**
 * Exception with a translatable error code
 *
 * The code maps to the language string 'err_<code>', the arguments are inserted
 * with sprintf(). getMessage() returns an English text for logs and tests.
 */
class H5PException extends \RuntimeException
{
    /** English fallback texts */
    public const MESSAGES = [
        'badkey'          => 'invalid content key',
        'notfound'        => 'file not found',
        'nocontent'       => 'content/content.json missing',
        'writepackage'    => 'could not write the package',
        'unknownlib'      => 'unknown library %s',
        'notzip'          => 'not a valid H5P (zip) file',
        'toomany'         => 'the package contains too many files',
        'unsafe'          => 'unsafe file name in the package: %s',
        'toolarge'        => 'the package is too large',
        'extract'         => 'could not extract %s',
        'storage'         => 'could not write to the storage directory (%s)',
        'nomainlib'       => 'h5p.json: mainLibrary missing',
        'badlibjson'      => '%s: invalid %s',
        'nolibjson'       => '%s: library.json missing',
        'jsonmissing'     => '%s missing',
        'jsoninvalid'     => '%s is not valid JSON',
        'badname'         => 'invalid machine name',
        'hub_answer'      => 'unexpected answer from the H5P Hub',
        'hub_save'        => 'could not save the download',
        'hub_register'    => 'registration at the H5P Hub failed',
        'hub_unreachable' => 'H5P Hub not reachable: %s',
        'hub_nopackage'   => 'the H5P Hub did not send a package for %s (%s)',
    ];

    protected string $errorCode;
    protected array $args;

    public function __construct(string $code, ...$args)
    {
        $this->errorCode = $code;
        $this->args = array_map('strval', $args);
        $template = self::MESSAGES[$code] ?? $code;
        parent::__construct(vsprintf($template, array_pad($this->args, substr_count($template, '%s'), '')));
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
