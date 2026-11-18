<?php

function login_throttle_bucket_keys(string $identifier, string $remoteAddress): array
{
    $normalizedIdentifier = strtolower(trim($identifier));

    return [
        [
            'key' => hash('sha256', "account\0" . $normalizedIdentifier . "\0" . $remoteAddress),
            'limit' => 5,
        ],
        [
            'key' => hash('sha256', "source\0" . $remoteAddress),
            'limit' => 30,
        ],
    ];
}

function login_throttle_with_store(callable $operation)
{
    $storePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'slectura-login-throttle-' . hash('sha256', dirname(__DIR__)) . '.json';
    $handle = fopen($storePath, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Login throttle storage could not be opened.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Login throttle storage could not be locked.');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false) {
            throw new RuntimeException('Login throttle storage could not be read.');
        }

        $records = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($records)) {
            throw new RuntimeException('Login throttle storage has an invalid format.');
        }

        $now = time();
        foreach ($records as $key => $record) {
            if (
                !is_string($key)
                || !is_array($record)
                || !isset($record['window_started_at'])
                || (int)$record['window_started_at'] < $now - 86400
            ) {
                unset($records[$key]);
            }
        }

        $result = $operation($records, $now);
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
            throw new RuntimeException('Login throttle storage could not be written.');
        }

        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function login_throttle_check(string $identifier, string $remoteAddress): array
{
    $buckets = login_throttle_bucket_keys($identifier, $remoteAddress);

    return login_throttle_with_store(static function (array &$records, int $now) use ($buckets): array {
        $retrySeconds = 0;
        foreach ($buckets as $bucket) {
            $record = $records[$bucket['key']] ?? null;
            if (!is_array($record)) {
                continue;
            }

            $bucketRetrySeconds = (int)($record['locked_until'] ?? 0) - $now;
            $retrySeconds = max($retrySeconds, $bucketRetrySeconds);
            if ($bucketRetrySeconds > 0) {
                continue;
            }

            if ((int)$record['window_started_at'] <= $now - 900) {
                unset($records[$bucket['key']]);
            }
        }

        return [
            'blocked' => $retrySeconds > 0,
            'retry_minutes' => $retrySeconds > 0 ? (int)ceil($retrySeconds / 60) : 0,
        ];
    });
}

function login_throttle_record_failure(string $identifier, string $remoteAddress): bool
{
    $buckets = login_throttle_bucket_keys($identifier, $remoteAddress);

    return login_throttle_with_store(static function (array &$records, int $now) use ($buckets): bool {
        $locked = false;
        foreach ($buckets as $bucket) {
            $key = $bucket['key'];
            $record = $records[$key] ?? null;
            if (
                !is_array($record)
                || (
                    (int)($record['locked_until'] ?? 0) <= $now
                    && (int)$record['window_started_at'] <= $now - 900
                )
            ) {
                $record = [
                    'attempt_count' => 0,
                    'window_started_at' => $now,
                    'locked_until' => 0,
                ];
            }

            if ((int)$record['locked_until'] > $now) {
                $locked = true;
                continue;
            }

            $record['attempt_count'] = (int)$record['attempt_count'] + 1;
            if ($record['attempt_count'] >= $bucket['limit']) {
                $record['locked_until'] = $now + 900;
                $locked = true;
            }
            $records[$key] = $record;
        }

        return $locked;
    });
}

function login_throttle_clear(string $identifier, string $remoteAddress): void
{
    $buckets = login_throttle_bucket_keys($identifier, $remoteAddress);

    login_throttle_with_store(static function (array &$records, int $now) use ($buckets): void {
        foreach ($buckets as $bucket) {
            unset($records[$bucket['key']]);
        }
    });
}
