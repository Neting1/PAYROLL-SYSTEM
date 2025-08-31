<?php
// Password Hash Generator
// Run this script to generate correct password hashes

echo "<h2>Password Hash Generator</h2>";

$passwords = [
    'admin123' => 'Admin password',
    'user123' => 'User password'
];

foreach ($passwords as $password => $description) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<strong>$description ($password):</strong><br>";
    echo "<code>$hash</code><br><br>";
}

echo "<hr>";
echo "<h3>SQL Update Statements:</h3>";
echo "<pre>";
echo "-- Update admin password\n";
echo "UPDATE users SET password_hash = '" . password_hash('admin123', PASSWORD_DEFAULT) . "' WHERE username = 'admin';\n\n";

echo "-- Update user passwords\n";
echo "UPDATE users SET password_hash = '" . password_hash('user123', PASSWORD_DEFAULT) . "' WHERE username = 'john_doe';\n";
echo "UPDATE users SET password_hash = '" . password_hash('user123', PASSWORD_DEFAULT) . "' WHERE username = 'jane_smith';\n";
echo "</pre>";
?>
