<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$mdFile = __DIR__ . '/README_APP.md';
$md = is_file($mdFile) ? (string)file_get_contents($mdFile) : "# Readme Not Found\n\n`docs/README_APP.md` is missing.";

function renderInline(string $text): string
{
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $safe = preg_replace_callback('/`([^`]+)`/', static function (array $m): string {
        return '<code>' . $m[1] . '</code>';
    }, $safe) ?? $safe;

    $safe = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
    $safe = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $safe) ?? $safe;
    $safe = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $safe) ?? $safe;

    return $safe;
}

function renderMarkdown(string $md): string
{
    $lines = preg_split('/\R/', $md) ?: [];
    $out = [];
    $inCode = false;
    $inUl = false;
    $inOl = false;
    $inP = false;

    $closeLists = static function () use (&$out, &$inUl, &$inOl): void {
        if ($inUl) {
            $out[] = '</ul>';
            $inUl = false;
        }
        if ($inOl) {
            $out[] = '</ol>';
            $inOl = false;
        }
    };

    $closeParagraph = static function () use (&$out, &$inP): void {
        if ($inP) {
            $out[] = '</p>';
            $inP = false;
        }
    };

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line) === 1) {
            $closeParagraph();
            $closeLists();
            if (!$inCode) {
                $out[] = '<pre><code>';
                $inCode = true;
            } else {
                $out[] = '</code></pre>';
                $inCode = false;
            }
            continue;
        }

        if ($inCode) {
            $out[] = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            continue;
        }

        if (trim($line) === '') {
            $closeParagraph();
            $closeLists();
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) === 1) {
            $closeParagraph();
            $closeLists();
            $level = strlen($m[1]);
            $out[] = '<h' . $level . '>' . renderInline($m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m) === 1) {
            $closeParagraph();
            if (!$inUl) {
                if ($inOl) {
                    $out[] = '</ol>';
                    $inOl = false;
                }
                $out[] = '<ul>';
                $inUl = true;
            }
            $out[] = '<li>' . renderInline($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m) === 1) {
            $closeParagraph();
            if (!$inOl) {
                if ($inUl) {
                    $out[] = '</ul>';
                    $inUl = false;
                }
                $out[] = '<ol>';
                $inOl = true;
            }
            $out[] = '<li>' . renderInline($m[1]) . '</li>';
            continue;
        }

        $closeLists();
        if (!$inP) {
            $out[] = '<p>';
            $inP = true;
        }
        $out[] = renderInline($line);
    }

    if ($inCode) {
        $out[] = '</code></pre>';
    }
    if ($inP) {
        $out[] = '</p>';
    }
    if ($inUl) {
        $out[] = '</ul>';
    }
    if ($inOl) {
        $out[] = '</ol>';
    }

    return implode("\n", $out);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Scrubber - About / Readme</title>
    <style>
        body {
            margin: 0;
            font-family: Georgia, "Palatino Linotype", serif;
            background: #f7f4ef;
            color: #1f2a37;
        }
        .wrap {
            max-width: 900px;
            margin: 32px auto;
            padding: 0 20px 32px;
        }
        .card {
            background: #fff;
            border: 1px solid #d9e1ea;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            padding: 24px;
            line-height: 1.5;
        }
        h1, h2, h3, h4, h5, h6 { margin-top: 24px; }
        h1 { margin-top: 0; }
        code {
            font-family: Menlo, Consolas, monospace;
            background: #f1f5f9;
            padding: 1px 5px;
            border-radius: 4px;
        }
        pre {
            background: #f8fafc;
            border: 1px solid #d9e1ea;
            border-radius: 8px;
            padding: 12px;
            overflow: auto;
        }
        pre code {
            background: transparent;
            padding: 0;
        }
        a { color: #1f6f8b; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <?php echo renderMarkdown($md); ?>
    </div>
</div>
</body>
</html>
