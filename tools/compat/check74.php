<?php
/**
 * Parses every PHP file against the PHP 7.4 grammar and reports anything that needs PHP 8.
 * Usage: php check74.php <dir> [<dir>...]
 */
$autoload = __DIR__ . '/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "The parser is not installed yet. Run this once:\n\n"
        . "    cd " . __DIR__ . " && composer install\n\n");
    exit(2);
}
require $autoload;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

$parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(7, 4));

// Functions introduced in PHP 8.0+ that would fatal on 7.4 unless polyfilled.
$php8Functions = [
    'str_contains' => '8.0', 'str_starts_with' => '8.0', 'str_ends_with' => '8.0',
    'get_debug_type' => '8.0', 'array_is_list' => '8.1', 'enum_exists' => '8.1',
    'fdiv' => '8.0', 'array_find' => '8.4', 'array_any' => '8.4', 'array_all' => '8.4',
    'json_validate' => '8.3', 'mb_str_pad' => '8.3', 'str_increment' => '8.3',
];
// Provided by kernel/polyfill.php, so safe to call on 7.4.
$polyfilled = ['str_contains', 'str_starts_with', 'str_ends_with'];

$php8Types = ['mixed' => '8.0', 'never' => '8.1', 'static' => '8.0', 'false' => '8.0', 'null' => '8.0'];

class Visitor extends NodeVisitorAbstract
{
    public $findings = [];
    public $file;
    public $php8Functions;
    public $polyfilled;
    public $php8Types;
    /** ids of Throw_ nodes used as plain statements, which 7.4 allows */
    private $statementThrows = [];

    private function typeNames($type): array
    {
        if ($type === null) return [];
        if ($type instanceof Node\NullableType) return $this->typeNames($type->type);
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            $out = ['__composite__'];
            foreach ($type->types as $t) $out = array_merge($out, $this->typeNames($t));
            return $out;
        }
        if ($type instanceof Node\Identifier) return [$type->toString()];
        // On the 7.4 grammar an unreserved type such as 'mixed' parses as a class Name.
        if ($type instanceof Node\Name) return [$type->toString()];
        return [];
    }

    private function checkType($type, int $line, string $what): void
    {
        foreach ($this->typeNames($type) as $name) {
            if ($name === '__composite__') {
                $this->findings[] = [$line, "union/intersection type in {$what} needs PHP 8.0"];
                continue;
            }
            $lower = strtolower($name);
            if (isset($this->php8Types[$lower])) {
                $this->findings[] = [$line, "type '{$lower}' in {$what} needs PHP {$this->php8Types[$lower]}"];
            }
        }
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\FunctionLike) {
            foreach ($node->getParams() as $p) {
                $this->checkType($p->type, $p->getLine(), 'parameter');
                // Before PHP 8, a parameter typed with a class may only default to NULL.
                $bare = $p->type instanceof Node\NullableType ? $p->type->type : $p->type;
                if ($bare instanceof Node\Name && $p->default !== null) {
                    $isNull = $p->default instanceof Node\Expr\ConstFetch
                        && strtolower($p->default->name->toString()) === 'null';
                    if (! $isNull) {
                        $this->findings[] = [$p->getLine(), "parameter typed '" . $bare->toString() . "' may only default to NULL before PHP 8.0"];
                    }
                }
                if ($p->flags !== 0) {
                    $this->findings[] = [$p->getLine(), 'constructor property promotion needs PHP 8.0'];
                }
            }
            $this->checkType($node->getReturnType(), $node->getLine(), 'return');
        }
        if ($node instanceof Node\Stmt\Property) {
            $this->checkType($node->type, $node->getLine(), 'property');
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $fn = strtolower($node->name->toString());
            if (isset($this->php8Functions[$fn]) && ! in_array($fn, $this->polyfilled, true)) {
                $this->findings[] = [$node->getLine(), "{$fn}() needs PHP {$this->php8Functions[$fn]}"];
            }
        }
        if ($node instanceof Node\AttributeGroup) {
            $this->findings[] = [$node->getLine(), 'attributes need PHP 8.0'];
        }

        // `throw` is a statement before PHP 8.0, so note the legal ones and flag the rest.
        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Throw_) {
            $this->statementThrows[spl_object_id($node->expr)] = true;
        }
        if ($node instanceof Node\Expr\Throw_ && ! isset($this->statementThrows[spl_object_id($node)])) {
            $this->findings[] = [$node->getLine(), 'throw used as an expression needs PHP 8.0'];
        }

        if ($node instanceof Node\Expr\Match_) {
            $this->findings[] = [$node->getLine(), 'match() needs PHP 8.0'];
        }
        if ($node instanceof Node\Expr\NullsafePropertyFetch || $node instanceof Node\Expr\NullsafeMethodCall) {
            $this->findings[] = [$node->getLine(), 'nullsafe operator ?-> needs PHP 8.0'];
        }
        if ($node instanceof Node\Arg && $node->name !== null) {
            $this->findings[] = [$node->getLine(), 'named arguments need PHP 8.0'];
        }
        if ($node instanceof Node\VariadicPlaceholder) {
            $this->findings[] = [$node->getLine(), 'first-class callable syntax needs PHP 8.1'];
        }
        if ($node instanceof Node\Stmt\Catch_ && $node->var === null) {
            $this->findings[] = [$node->getLine(), 'catch without a variable needs PHP 8.0'];
        }
        if ($node instanceof Node\Stmt\Enum_) {
            $this->findings[] = [$node->getLine(), 'enums need PHP 8.1'];
        }
        if ($node instanceof Node\Expr\ClassConstFetch
            && $node->name instanceof Node\Identifier && strtolower($node->name->toString()) === 'class'
            && ! ($node->class instanceof Node\Name)) {
            $this->findings[] = [$node->getLine(), '$object::class needs PHP 8.0'];
        }
        if ($node instanceof Node\Stmt\ClassConst && ($node->flags & Node\Stmt\Class_::MODIFIER_FINAL)) {
            $this->findings[] = [$node->getLine(), 'final class constants need PHP 8.1'];
        }
        if (($node instanceof Node\Stmt\Property || $node instanceof Node\Param)
            && defined('PhpParser\\Modifiers::READONLY')
            && ($node->flags & \PhpParser\Modifiers::READONLY)) {
            $this->findings[] = [$node->getLine(), 'readonly needs PHP 8.1'];
        }
        // Array unpacking itself is fine on 7.4, but only for list arrays: string keys
        // in the unpacked array are PHP 8.1. That is a runtime property, so flag it for review.
        if (($node instanceof Node\ArrayItem || $node instanceof Node\Expr\ArrayItem) && $node->unpack) {
            $this->findings[] = [$node->getLine(), 'array unpacking: 7.4 supports list arrays only, string keys need PHP 8.1'];
        }
        return null;
    }
}

$dirs = array_slice($argv, 1);
$files = [];
foreach ($dirs as $dir) {
    if (is_file($dir)) { $files[] = $dir; continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname();
    }
}
sort($files);

$errors = 0;
foreach ($files as $file) {
    $code = file_get_contents($file);
    try {
        $ast = $parser->parse($code);
    } catch (PhpParser\Error $e) {
        echo "SYNTAX  {$file}:{$e->getStartLine()}  {$e->getRawMessage()}\n";
        $errors++;
        continue;
    }
    $v = new Visitor();
    $v->file = $file;
    $v->php8Functions = $php8Functions;
    $v->polyfilled = $polyfilled;
    $v->php8Types = $php8Types;
    $t = new NodeTraverser();
    $t->addVisitor($v);
    $t->traverse($ast);
    foreach ($v->findings as [$line, $msg]) {
        echo "PHP8    {$file}:{$line}  {$msg}\n";
        $errors++;
    }
}
echo "\nScanned " . count($files) . " files, {$errors} problem(s) for PHP 7.4.\n";
exit($errors > 0 ? 1 : 0);
