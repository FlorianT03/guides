<?php

declare(strict_types=1);

namespace phpDocumentor\Guides;

use InvalidArgumentException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri as LeagueUri;
use Throwable;

use function preg_match;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

//TODO remove this, as it is copied form phpDocumentor to make things work.
final class UriFactory
{
    public const WINDOWS_URI_FORMAT = '~^(file:\/\/\/)?(?<root>[a-zA-Z][:|\|])~';

    public static function createUri(string $uriString): UriInterface
    {
        try {
            $uriString = str_replace(DIRECTORY_SEPARATOR, '/', $uriString);
            if (str_starts_with($uriString, 'phar://')) {
                return self::createPharUri($uriString);
            }

            if (preg_match(self::WINDOWS_URI_FORMAT, $uriString)) {
                if (str_starts_with($uriString, 'file:///')) {
                    $uriString = substr($uriString, strlen('file:///'));
                }

                return LeagueUri::createFromWindowsPath($uriString);
            }

            return LeagueUri::createFromString($uriString);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'The uri "%s" could not be parsed, the following error occured: %s',
                    $uriString,
                    $exception->getMessage(),
                ),
                0,
                $exception,
            );
        }
    }

    private static function createPharUri(string $uriString): UriInterface
    {
        $path = substr($uriString, strlen('phar://'));
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return LeagueUri::createFromComponents(
            [
                'scheme' => 'phar',
                'host' => '',
                'path' => $path,
            ],
        );
    }
}
