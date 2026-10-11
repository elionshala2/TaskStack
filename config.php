<?php
// config.php
require_once __DIR__ . '/vendor/autoload.php'; // autoload per te ngarkuar Dotenv dhe librarite e tjera nga composer
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // krijimi i instances se Dotenv per te lexuar variablat e mjedisit nga .env
$dotenv->load(); // ngarkon variablat e mjedisit nga .env ne $_ENV

$server   = $_ENV['DB_SERVER']; // serveri i databazes
$user     = $_ENV['DB_USER']; // perdoruesi i databazes
$pass     = $_ENV['DB_PASS']; // passwordi i databazes
$database = $_ENV['DB_DATABASE']; // emri i databazes
$google_client_id     = $_ENV['GOOGLE_CLIENT_ID'];
$google_client_secret = $_ENV['GOOGLE_CLIENT_SECRET'];
$google_redirect_uri  = $_ENV['GOOGLE_REDIRECT_URI'];

// fshehja e qdo gabimi prej outputit (shfaqen vetem ne log qe mos me i pa perdoruesi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($server, $user, $pass, $database);
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // gabimi ruhet vetem ne log
    error_log("DB Error: " . $e->getMessage());

    // perdoruesi e sheh vetem kete mesazh
    http_response_code(500);
    exit("Diqka shkoi gabim. Ju lutem provoni perseri me vone.");
}
