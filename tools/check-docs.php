<?php
/**
 * check-docs — assert the reference still matches the application.
 *
 * Sibling of karhu's tools/check-docs.php, the shared TypeScript gate in otso/datvi,
 * wojtek's routes-and-commands gate and paddington's provisioned-YAML gate. Same premise
 * throughout: docs that are confidently wrong are worse than docs that are absent. Absent
 * docs send you to the source; wrong docs send you nowhere and you believe them. karhu's
 * README shipped a fatally broken hello-world for months because nothing checked.
 *
 * WHY THIS IS NOT karhu's GATE, despite both being PHP. karhu's reflects over src/ and
 * requires every public METHOD of a library to be documented — the right contract for
 * something people call from their own code. istrbuddy is an application: nobody imports
 * App\Controllers\IssueController. What it offers a reader is its ROUTES, its console
 * commands and its roles, so those are what must not rot.
 *
 * Three assertions:
 *   1. every #[Route] in app/ is documented
 *   2. the docs do not claim a route that no longer exists
 *   3. every cited app/ or config/ path exists
 *
 * Deliberately regex over the attribute text rather than Reflection: reading these
 * attributes properly would need the container booted and karhu autoloaded, which turns a
 * two-second check into an install. The attribute lines are one-per-method and syntactically
 * shallow. The risk of a regex silently matching nothing is covered by the floor below.
 *
 * Usage: php tools/check-docs.php   (exit 1 on any error)
 */

$root   = dirname(__DIR__);
$docsDir = $root . '/docs';
$errors = [];

/** Recursively collect files under a directory with one of the given extensions. */
function walk(string $dir, array $exts): array {
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && in_array($file->getExtension(), $exts, true)) {
            $out[] = $file->getPathname();
        }
    }
    return $out;
}

// ------------------------------------------------------------------ docs corpus
$docFiles = walk($docsDir, ['md']);
if ($docFiles === []) {
    $errors[] = 'docs/ contains no markdown';
}
$allDocs = '';
foreach ($docFiles as $f) {
    $allDocs .= file_get_contents($f) . "\n";
}

// ------------------------------------------------- 1 + 2. routes <-> the reference
// Matches #[Route('/issues/{id}/status', methods: ['POST'], name: 'issues.status')] and the
// shorter forms. The path is what is asserted; the method list is not required to appear in
// the same span, because GET and POST /login are one row in the reference and splitting them
// to satisfy a tool would be the tool dictating the prose.
$routes = [];
foreach (walk($root . '/app', ['php']) as $file) {
    $text = file_get_contents($file);
    if (preg_match_all("/#\[Route\(\s*'([^']+)'/", $text, $m)) {
        foreach ($m[1] as $path) {
            $routes[$path] = str_replace($root . '/', '', $file);
        }
    }
}

// A SANITY FLOOR, not a style rule. If routing moved off attributes — to a route file, or a
// builder — this regex would find nothing, every check below would iterate an empty list, and
// the gate would pass while asserting precisely nothing. A green gate that checks nothing is
// worse than no gate at all.
if (count($routes) < 6) {
    $errors[] = sprintf(
        'found only %d #[Route] attributes in app/ (expected >= 6) — routing has probably moved; '
        . 'check how routes are declared before lowering this',
        count($routes)
    );
}

// Documented = the exact path in a code span. Route paths contain braces, so they are escaped
// rather than interpolated into the pattern.
foreach ($routes as $path => $file) {
    $needle = '`' . $path . '`';
    if (!str_contains($allDocs, $needle)) {
        $errors[] = sprintf('undocumented route: %s (declared in %s) — add it to docs/routes.md', $path, $file);
    }
}

// The reverse. Only the first column of a table headed `Route` counts as a claim, so prose and
// the roles table are free to mention whatever they need — the same lesson the shared package
// gate learned when prop tables produced 21 false positives on otso.
$claimed = [];
foreach ($docFiles as $f) {
    $lines = explode("\n", file_get_contents($f));
    $inRouteTable = false;
    foreach ($lines as $i => $line) {
        if (preg_match('/^\|\s*([A-Za-z ]+?)\s*\|/', $line, $h)
            && isset($lines[$i + 1]) && preg_match('/^\|[\s:|-]+\|\s*$/', $lines[$i + 1])) {
            $inRouteTable = strtolower(trim($h[1])) === 'route';
            continue;
        }
        if (!str_starts_with($line, '|')) {
            $inRouteTable = false;
            continue;
        }
        if ($inRouteTable && preg_match('/^\|\s*`([^`]+)`/', $line, $r)) {
            $claimed[] = $r[1];
        }
    }
}
foreach (array_unique($claimed) as $c) {
    if ($routes !== [] && !array_key_exists($c, $routes)) {
        $errors[] = sprintf('documented but not routed: %s (docs claim it; no #[Route] declares it)', $c);
    }
}

// -------------------------------------------------- 3. cited paths must exist
// The commonest rot by far: a file is renamed and the prose keeps pointing at the old name.
$cited = [];
if (preg_match_all('/`((?:app|config|db|public|tests)\/[A-Za-z0-9_.\/-]+\.(?:php|sql|json))`/', $allDocs, $m)) {
    $cited = array_unique($m[1]);
}
sort($cited);
foreach ($cited as $p) {
    if (!file_exists($root . '/' . $p)) {
        $errors[] = 'dead path cited in docs: ' . $p;
    }
}

// ------------------------------------------------------------------------ report
foreach ($errors as $e) {
    fwrite(STDERR, "ERROR  $e\n");
}
printf(
    "checked istrbuddy: %d route(s), %d doc file(s), %d cited path(s) — %d error(s)\n",
    count($routes),
    count($docFiles),
    count($cited),
    count($errors)
);
exit($errors === [] ? 0 : 1);
