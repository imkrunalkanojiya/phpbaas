<?php
// Maintenance mode configuration
return [
    'enabled' => false, // Set to true to enable maintenance mode
    'allowed_ips' => [
        '127.0.0.1', // Localhost
        // Add your IP address here when testing
    ],
    'message' => 'We are currently performing scheduled maintenance. Please check back soon.',
    'estimated_duration' => '2 hours', // Optional: Estimated maintenance duration
]; 