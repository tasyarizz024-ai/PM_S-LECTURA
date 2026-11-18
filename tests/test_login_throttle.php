<?php

require_once __DIR__ . '/../includes/login_throttle.php';

function assertThrottleTest(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
}

$runId = bin2hex(random_bytes(6));
$account = 'student-' . $runId . '@example.test';
$accountAddress = '192.0.2.' . $runId;

for ($attempt = 1; $attempt <= 5; $attempt++) {
    $state = login_throttle_check($account, $accountAddress);
    assertThrottleTest(!$state['blocked'], 'Account/source bucket blocked before the fifth failure.');
    $locked = login_throttle_record_failure($account, $accountAddress);
    assertThrottleTest($locked === ($attempt === 5), 'Account/source lock did not begin on the fifth failure.');
}

$blockedAccount = login_throttle_check($account, $accountAddress);
assertThrottleTest($blockedAccount['blocked'] && $blockedAccount['retry_minutes'] > 0, 'Locked account/source bucket was not rejected.');
login_throttle_clear($account, $accountAddress);
echo "[PASS] Account/source bucket locks after five failures.\n";

$sourceAddress = '198.51.100.' . $runId;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    $identifier = 'user-' . $attempt . '-' . $runId . '@example.test';
    $state = login_throttle_check($identifier, $sourceAddress);
    assertThrottleTest(!$state['blocked'], 'Source bucket blocked before the thirtieth failure.');
    $locked = login_throttle_record_failure($identifier, $sourceAddress);
    assertThrottleTest($locked === ($attempt === 30), 'Source-wide lock did not begin on the thirtieth failure.');
}

$blockedSource = login_throttle_check('another-user-' . $runId . '@example.test', $sourceAddress);
assertThrottleTest($blockedSource['blocked'] && $blockedSource['retry_minutes'] > 0, 'Locked source bucket was not rejected.');
login_throttle_clear('another-user-' . $runId . '@example.test', $sourceAddress);
echo "[PASS] Source bucket locks after thirty failures.\n";

$successAccount = 'success-' . $runId . '@example.test';
$successAddress = '203.0.113.' . $runId;
for ($attempt = 1; $attempt <= 4; $attempt++) {
    login_throttle_check($successAccount, $successAddress);
    login_throttle_record_failure($successAccount, $successAddress);
}
login_throttle_clear($successAccount, $successAddress);
for ($attempt = 1; $attempt <= 4; $attempt++) {
    $state = login_throttle_check($successAccount, $successAddress);
    assertThrottleTest(!$state['blocked'], 'Successful-login reset did not clear the counters.');
    $locked = login_throttle_record_failure($successAccount, $successAddress);
    assertThrottleTest(!$locked, 'Counter was not reset after a successful login.');
}
$locked = login_throttle_record_failure($successAccount, $successAddress);
assertThrottleTest($locked, 'Bucket did not lock after five failures following a reset.');
login_throttle_clear($successAccount, $successAddress);
echo "[PASS] Successful login clears account and source buckets.\n";
