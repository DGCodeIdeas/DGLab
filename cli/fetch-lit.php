<?php

$baseUrl = "https://unpkg.com/";
$targetDir = __DIR__ . "/../public/vendor/lit";
$vendorMapPath = __DIR__ . "/../config/vendor_map.php";

if (is_dir($targetDir)) system("rm -rf " . escapeshellarg($targetDir));
mkdir($targetDir, 0755, true);

$filesToFetch = [
    'lit@3.2.0/index.js' => 'index.js',
    'lit-html@3.2.0/lit-html.js' => 'lit-html.js',
    'lit-html@3.2.0/is-server.js' => 'is-server.js',
    'lit-html@3.2.0/directive.js' => 'directive.js',
    'lit-html@3.2.0/async-directive.js' => 'async-directive.js',
    'lit-html@3.2.0/directive-helpers.js' => 'directive-helpers.js',
    'lit-element@4.1.0/lit-element.js' => 'lit-element.js',
    'lit-element@4.1.0/index.js' => 'lit-element/index.js',
    '@lit/reactive-element@2.1.0/reactive-element.js' => 'reactive-element.js',
    '@lit/reactive-element@2.1.0/css-tag.js' => 'css-tag.js',
    'lit@3.2.0/directives/until.js' => 'directives/until.js',
    'lit-html@3.2.0/directives/until.js' => 'lit-html/directives/until.js',
    'lit@3.2.0/directives/repeat.js' => 'directives/repeat.js',
    'lit-html@3.2.0/directives/repeat.js' => 'lit-html/directives/repeat.js',
    'lit@3.2.0/directives/if-defined.js' => 'directives/if-defined.js',
    'lit-html@3.2.0/directives/if-defined.js' => 'lit-html/directives/if-defined.js',
    'lit@3.2.0/directives/class-map.js' => 'directives/class-map.js',
    'lit-html@3.2.0/directives/class-map.js' => 'lit-html/directives/class-map.js',
    'lit@3.2.0/directives/style-map.js' => 'directives/style-map.js',
    'lit-html@3.2.0/directives/style-map.js' => 'lit-html/directives/style-map.js',
    'lit-html@3.2.0/directives/private-async-helpers.js' => 'lit-html/directives/private-async-helpers.js',
];

$vendorMap = [
    'lit' => '/vendor/lit/index.js',
    'lit-html' => '/vendor/lit/lit-html.js',
    'lit-html/is-server.js' => '/vendor/lit/is-server.js',
    'lit-element/lit-element.js' => '/vendor/lit/lit-element.js',
    '@lit/reactive-element' => '/vendor/lit/reactive-element.js',
];

foreach ($filesToFetch as $remote => $local) {
    $url = $baseUrl . $remote . "?module";
    echo "Fetching: $url -> $local\n";
    $content = file_get_contents($url);
    if ($content) {
        $fullPath = $targetDir . "/" . $local;
        if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $content);
        if (strpos($local, 'directives/') === 0 && strpos($local, 'lit-html/') === false) {
            $name = basename($local, '.js');
            $vendorMap["lit/directives/$name"] = "/vendor/lit/$local";
        }
    }
}

ksort($vendorMap);
file_put_contents($vendorMapPath, "<?php\nreturn " . var_export($vendorMap, true) . ";\n");
echo "Done\n";
