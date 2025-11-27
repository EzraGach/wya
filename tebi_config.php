<?php
/**
 * Tebi S3 Configuration
 * Uses environment variables for security - never commit credentials
 */

$s3Client = null;

// Check if composer dependencies are installed
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    
    // Get credentials from environment variables
    $tebi_key = getenv('TEBI_KEY');
    $tebi_secret = getenv('TEBI_SECRET');
    
    // Only initialize S3 client if credentials are provided
    if ($tebi_key && $tebi_secret) {
        try {
            $s3Client = new Aws\S3\S3Client([
                "credentials" => [
                    "key"    => $tebi_key,
                    "secret" => $tebi_secret
                ],
                "endpoint" => getenv('TEBI_ENDPOINT') ?: "https://s3.tebi.io",
                "region"   => getenv('TEBI_REGION') ?: "de",
                "version"  => "2006-03-01"
            ]);
        } catch (Exception $e) {
            error_log("Tebi S3 initialization failed: " . $e->getMessage());
            $s3Client = null;
        }
    }
}