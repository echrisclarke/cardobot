<?php
/**
 * Idempotent schema apply for Railway / Docker boot.
 * Safe to re-run: schema.sql uses CREATE TABLE IF NOT EXISTS.
 */

$host = getenv('MYSQLHOST') ?: getenv('CARDOBOT_DB_HOST') ?: '';
$port = getenv('MYSQLPORT') ?: getenv('CARDOBOT_DB_PORT') ?: '3306';
$db   = getenv('MYSQLDATABASE') ?: getenv('CARDOBOT_DB_NAME') ?: '';
$user = getenv('MYSQLUSER') ?: getenv('CARDOBOT_DB_USER') ?: '';
$pass = getenv('MYSQLPASSWORD') ?: getenv('CARDOBOT_DB_PASS') ?: '';

if ($host === '' || $db === '' || $user === '') {
  fwrite(STDERR, "migrate: missing DB credentials\n");
  exit(1);
}

$schemaPath = __DIR__ . '/schema.sql';
$sql = @file_get_contents($schemaPath);
if ($sql === false || trim($sql) === '') {
  fwrite(STDERR, "migrate: schema.sql missing\n");
  exit(1);
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$attempts = 0;
$pdo = null;
while ($attempts < 30) {
  $attempts++;
  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    break;
  } catch (Throwable $e) {
    fwrite(STDERR, "migrate: waiting for MySQL ({$attempts}/30)\n");
    sleep(2);
  }
}

if (!$pdo) {
  fwrite(STDERR, "migrate: could not connect to MySQL\n");
  exit(1);
}

$lines = preg_split("/\R/", $sql) ?: [];
$buf = [];
foreach ($lines as $line) {
  if (preg_match('/^\s*--/', $line)) {
    continue;
  }
  $buf[] = $line;
}
$clean = implode("\n", $buf);

foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
  if ($stmt === '') {
    continue;
  }
  $pdo->exec($stmt);
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
fwrite(STDOUT, 'migrate: ok tables=' . implode(',', $tables ?: []) . "\n");
exit(0);
