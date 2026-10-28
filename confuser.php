<?php
// simple php confuser/obfuscator

function obfuscate_php($code, $options) {

    if (!empty($options['functions'])) {
        preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)/m', $code, $matches);
        $functions = $matches[1];

        $functionMap = [];
        foreach ($functions as $fn) {
            $functionMap[$fn] = 'fn_' . bin2hex(random_bytes(4));
        }

        foreach ($functionMap as $original => $newName) {

            $code = preg_replace(
                '/function\s+' . preg_quote($original) . '\s*\(/',
                "function $newName(",
                $code
            );

            $code = preg_replace(
                '/\b' . preg_quote($original) . '\s*\(/',
                "$newName(",
                $code
            );
        }
    }

    if (!empty($options['variables'])) {
        preg_match_all('/\$[a-zA-Z_][a-zA-Z0-9_]*/', $code, $varMatches);
        $variables = array_unique($varMatches[0]);

        $skipVars = [
            '$_GET','$_POST','$_REQUEST','$_COOKIE','$_SESSION',
            '$_ENV','$_SERVER','$_FILES','$_GLOBALS',
            '$php_errormsg','$HTTP_RAW_POST_DATA',
            '$argc','$argv'
        ];

        $varMap = [];
        foreach ($variables as $var) {
            if (!in_array($var, $skipVars)) {
                $varMap[$var] = '$v_' . bin2hex(random_bytes(3));
            }
        }

        foreach ($varMap as $original => $newName) {
            $code = preg_replace(
                '/(?<![a-zA-Z0-9_])' . preg_quote($original, '/') . '(?![a-zA-Z0-9_])/',
                $newName,
                $code
            );
        }
    }

    if (!empty($options['comments'])) {
        $code = preg_replace('/\/\/.*$/m', '', $code);
    }

    if (!empty($options['strings'])) {
        $code = preg_replace_callback(
            '/(["\'])(.*?)\1/s',
            function ($m) {
                $decoded = stripcslashes($m[2]);
                $encoded = base64_encode($decoded);
                return 'base64_decode("' . $encoded . '")';
            },
            $code
        );
    }

    if (!empty($options['blanklines'])) {
        $code = preg_replace('/^\s*$/m', '', $code);
        $code = preg_replace("/\n{2,}/", "\n", $code);
    }

    $code = preg_replace('/\s+/', '', $code);
    $code = str_replace('<?php', '<?php ', $code);
    $code = str_replace('?>', ' ?>', $code);
    
    return $code;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP Confuser</title>

    <style>
        body {
            font-family: Consolas, monospace;
            background: #1e1e1e;
            color: #ddd;
            padding: 40px;
        }

        h2 {
            color: #f5f5f5;
        }

        .box {
            padding: 20px;
            background: #2b2b2b;
            border: 1px solid #444;
            border-radius: 6px;
        }

        input[type="file"] {
            color: #fff;
        }

        .options label {
            display: block;
            margin: 6px 0;
            cursor: pointer;
        }

        textarea {
            width: 100%;
            height: 320px;
            background: #111;
            color: #0f0;
            border: 1px solid #555;
            padding: 10px;
            font-family: monospace;
            resize: none;
        }

        button {
            background: #4a4a4a;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 4px;
        }

        button:hover {
            background: #666;
        }

        .download-link {
            color: #9ad6ff;
            text-decoration: none;
            margin-top: 10px;
            display: inline-block;
        }

        .download-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<h2>PHP Obfuscator</h2>

<div class="box">
    <form method="post" enctype="multipart/form-data">

        <label>Select PHP file:</label><br><br>
        <input type="file" name="php_file" accept=".php" required><br><br>

        <div class="options">
            <label><input type="checkbox" name="steps[]" value="functions" checked> Confuser function names</label>
            <label><input type="checkbox" name="steps[]" value="variables" checked> Confuser variable names</label>
            <label><input type="checkbox" name="steps[]" value="comments" checked> Remove comments</label>
            <label><input type="checkbox" name="steps[]" value="strings" checked> Confuser strings</label>
            <label><input type="checkbox" name="steps[]" value="blanklines" checked> Remove blank lines</label>
        </div>

        <br>
        <button type="submit">Confuser</button>
    </form>
</div>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["php_file"])) {

    $fileTmp = $_FILES["php_file"]["tmp_name"];
    $originalName = basename($_FILES["php_file"]["name"]);
    $code = file_get_contents($fileTmp);

    $selected = isset($_POST['steps']) ? $_POST['steps'] : [];

    $options = [
        'functions'  => in_array('functions', $selected),
        'variables'  => in_array('variables', $selected),
        'comments'   => in_array('comments', $selected),
        'strings'    => in_array('strings', $selected),
        'blanklines' => in_array('blanklines', $selected),
    ];

    $obfuscated = obfuscate_php($code, $options);

    $outputName = "obfuscated_" . $originalName;
    file_put_contents($outputName, $obfuscated);

    echo "<h3>Obfuscated Output</h3>";
    echo "<textarea readonly>$obfuscated</textarea>";

    echo "<br><a class='download-link' href='$outputName' download>Download</a>";
}
?>

</body>
</html>
