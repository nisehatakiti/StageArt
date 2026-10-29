<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

/**
 * A scriptable stand-in for WordPress's $wpdb, for tests that run real
 * Infrastructure code (repositories, installers, migrations) without a
 * database. Every call is recorded. prepare() remembers the template and
 * arguments of the string it returns, so a responder can look at
 * structured input instead of parsing SQL.
 *
 * Use through tests/Support/wordpress-stubs.php, which declares the
 * global `wpdb` class (needed by type-hinted constructors) - and only in
 * @runInSeparateProcess tests, so no other test sees those globals.
 */
class FakeWpdb
{
    public string $prefix = 'wp_';

    /** @var array<int, array{method: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var array<string, array{template: string, args: array<int, mixed>}> */
    private array $prepared = [];

    /** @var callable(string, array<int, mixed>, FakeWpdb): mixed|null */
    private $responder;

    /**
     * @param callable(string, array<int, mixed>, FakeWpdb): mixed|null $responder
     *        receives the method name and its arguments; its return value is
     *        returned to the caller. Without one, reads return nothing and
     *        writes report one affected row.
     */
    public function __construct(?callable $responder = null)
    {
        $this->responder = $responder;
    }

    public function prepare(string $query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $index = 0;
        $sql = preg_replace_callback('/%[sd]/', static function (array $match) use ($args, &$index): string {
            $value = $args[$index++] ?? null;

            return $match[0] === '%d' ? (string) (int) $value : "'" . addslashes((string) $value) . "'";
        }, $query);

        $this->prepared[$sql] = ['template' => $query, 'args' => $args];

        return $sql;
    }

    /**
     * @return array{template: string, args: array<int, mixed>}|null
     */
    public function preparedFrom(string $sql): ?array
    {
        return $this->prepared[$sql] ?? null;
    }

    public function get_charset_collate(): string
    {
        return '';
    }

    public function esc_like(string $text): string
    {
        return $text;
    }

    public function query($sql)
    {
        return $this->respond('query', [$sql], 1);
    }

    public function get_results($sql, $output = null)
    {
        return $this->respond('get_results', [$sql, $output], []);
    }

    public function get_row($sql, $output = null)
    {
        return $this->respond('get_row', [$sql, $output], null);
    }

    public function get_var($sql)
    {
        return $this->respond('get_var', [$sql], null);
    }

    public function get_col($sql)
    {
        return $this->respond('get_col', [$sql], []);
    }

    public function insert($table, $data, $format = null)
    {
        return $this->respond('insert', [$table, $data], 1);
    }

    public function update($table, $data, $where, $format = null, $whereFormat = null)
    {
        return $this->respond('update', [$table, $data, $where], 1);
    }

    public function delete($table, $where, $whereFormat = null)
    {
        return $this->respond('delete', [$table, $where], 1);
    }

    /**
     * @return array<int, array<int, mixed>> the argument lists of every call to $method
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(
            static fn (array $call): array => $call['args'],
            array_filter($this->calls, static fn (array $call): bool => $call['method'] === $method)
        ));
    }

    /**
     * @param array<int, mixed> $args
     * @param mixed $default
     * @return mixed
     */
    private function respond(string $method, array $args, $default)
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if ($this->responder === null) {
            return $default;
        }

        $result = ($this->responder)($method, $args, $this);

        return $result === FakeWpdbDefault::VALUE ? $default : $result;
    }
}
