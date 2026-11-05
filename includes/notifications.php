<?php

function create_notification(PDO $pdo, int $userId, string $message, string $url): void {
    if ($userId <= 0 || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
        throw new InvalidArgumentException('Penerima dan tautan notifikasi tidak valid.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO notifikasi (user_id, pesan, tautan) VALUES (:user_id, :pesan, :tautan)"
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':pesan' => mb_substr($message, 0, 255),
        ':tautan' => $url,
    ]);
}
