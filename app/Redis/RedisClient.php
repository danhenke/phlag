<?php

declare(strict_types=1);

namespace Phlag\Redis;

final class RedisClient
{
    private const DEFAULT_TIMEOUT = 5.0;

    private ?\Socket $socket = null;

    /**
     * @var resource|null
     */
    private $stream = null;

    private readonly string $host;

    private readonly int $port;

    private readonly ?string $password;

    private readonly int $database;

    private readonly float $timeout;

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        if (isset($config['url']) && is_string($config['url']) && $config['url'] !== '') {
            $parts = parse_url($config['url']);

            if ($parts === false) {
                throw new \InvalidArgumentException(sprintf('Unable to parse Redis URL [%s].', $config['url']));
            }

            $host = $parts['host'] ?? '127.0.0.1';
            $port = isset($parts['port']) ? (int) $parts['port'] : 6379;
            $password = isset($parts['pass']) ? (string) $parts['pass'] : null;
            $path = $parts['path'] ?? '';
            $database = $path !== '' ? (int) ltrim($path, '/') : (int) ($config['database'] ?? 0);

            return new self(
                host: $host,
                port: $port,
                password: $password ?? (is_string($config['password'] ?? null) ? $config['password'] : null),
                database: $database,
                timeout: isset($config['timeout']) ? (float) $config['timeout'] : self::DEFAULT_TIMEOUT
            );
        }

        return new self(
            host: is_string($config['host'] ?? null) ? $config['host'] : '127.0.0.1',
            port: isset($config['port']) ? (int) $config['port'] : 6379,
            password: is_string($config['password'] ?? null) ? $config['password'] : null,
            database: isset($config['database']) ? (int) $config['database'] : 0,
            timeout: isset($config['timeout']) ? (float) $config['timeout'] : self::DEFAULT_TIMEOUT
        );
    }

    public function __construct(
        string $host,
        int $port,
        ?string $password = null,
        int $database = 0,
        float $timeout = self::DEFAULT_TIMEOUT
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
        $this->timeout = $timeout;
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function get(string $key): ?string
    {
        $response = $this->execute('GET', [$key]);

        if ($response === null) {
            return null;
        }

        if (! is_string($response)) {
            throw new \RuntimeException('Unexpected Redis response type for GET command.');
        }

        return $response;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->execute('SETEX', [$key, (string) $ttl, $value]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function del(array $keys): int
    {
        if ($keys === []) {
            return 0;
        }

        $response = $this->execute('DEL', $keys);

        return is_int($response) ? $response : 0;
    }

    /**
     * @param  array<int, string>  $members
     */
    public function sadd(string $key, array $members): void
    {
        if ($members === []) {
            return;
        }

        $this->execute('SADD', array_merge([$key], $members));
    }

    /**
     * @return array<int, string>
     */
    public function smembers(string $key): array
    {
        $response = $this->execute('SMEMBERS', [$key]);

        if (! is_array($response)) {
            return [];
        }

        return array_values(array_map(static fn ($value): string => (string) $value, $response));
    }

    public function expire(string $key, int $ttl): void
    {
        $this->execute('EXPIRE', [$key, (string) $ttl]);
    }

    public function publish(string $channel, string $message): void
    {
        $this->execute('PUBLISH', [$channel, $message]);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function execute(string $command, array $arguments = []): mixed
    {
        $this->ensureConnection();

        $payload = $this->formatCommand($command, $arguments);

        $written = fwrite($this->stream, $payload);

        if ($written === false || $written !== strlen($payload)) {
            throw new \RuntimeException(sprintf('Failed to write Redis command [%s].', $command));
        }

        return $this->readResponse();
    }

    private function ensureConnection(): void
    {
        if (is_resource($this->stream)) {
            return;
        }

        $address = sprintf('tcp://%s:%d', $this->host, $this->port);

        $stream = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! is_resource($stream)) {
            throw new \RuntimeException(sprintf(
                'Unable to connect to Redis at %s: %s (%d)',
                $address,
                $errstr !== '' ? $errstr : 'unknown error',
                $errno
            ));
        }

        stream_set_timeout($stream, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1_000_000));

        $this->stream = $stream;

        if ($this->password !== null && $this->password !== '') {
            $this->execute('AUTH', [$this->password]);
        }

        if ($this->database > 0) {
            $this->execute('SELECT', [(string) $this->database]);
        }
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function formatCommand(string $command, array $arguments): string
    {
        $parts = array_merge([$command], $arguments);
        $segments = sprintf('*%d', count($parts));

        foreach ($parts as $part) {
            $segment = (string) $part;
            $segments .= sprintf("\r\n$%d\r\n%s", strlen($segment), $segment);
        }

        return $segments."\r\n";
    }

    private function readResponse(): mixed
    {
        $line = $this->readLine();

        if ($line === '') {
            throw new \RuntimeException('Redis connection closed unexpectedly.');
        }

        $prefix = $line[0];
        $payload = substr($line, 1);

        return match ($prefix) {
            '+' => $payload,
            '-' => throw new \RuntimeException(sprintf('Redis error: %s', $payload)),
            ':' => (int) $payload,
            '$' => $this->readBulkString((int) $payload),
            '*' => $this->readArray((int) $payload),
            default => throw new \RuntimeException(sprintf('Unknown Redis response type [%s].', $prefix)),
        };
    }

    private function readLine(): string
    {
        if (! is_resource($this->stream)) {
            throw new \RuntimeException('Redis connection not established.');
        }

        $line = fgets($this->stream);

        if ($line === false) {
            throw new \RuntimeException('Failed to read from Redis stream.');
        }

        return rtrim($line, "\r\n");
    }

    private function readBulkString(int $length): ?string
    {
        if ($length === -1) {
            return null;
        }

        if (! is_resource($this->stream)) {
            throw new \RuntimeException('Redis connection not established.');
        }

        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Failed to read Redis bulk string.');
            }

            $data .= $chunk;
        }

        // Consume trailing CRLF.
        fread($this->stream, 2);

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function readArray(int $length): array
    {
        if ($length === -1) {
            return [];
        }

        $items = [];

        for ($i = 0; $i < $length; $i++) {
            $items[] = $this->readResponse();
        }

        return $items;
    }
}
