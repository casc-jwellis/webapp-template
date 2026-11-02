<?php

declare(strict_types=1);

namespace App\Modules\Items;

/**
 * The advanced filters on the item list ("Filter" button): pick-from-a-list, "contains" text, a quantity range and
 * date ranges. The model for the filter panel of any list in an app made from this template.
 *
 * Everything arrives in the address as ?f[...]=...; fromQuery() keeps only values it can validate, and the SQL below is
 * built from fixed column names with every value passed as a parameter, so nothing typed can reach a query. The same
 * validated values are what go back into links (query()), the active-filter chips and the CSV export.
 */
final class ItemFilter
{
    /** Pick from a list: key => [column, label]. The choices are ItemsModule::CATEGORIES. */
    public const PICKS = [
        'cat' => ['i.category', 'Category'],
    ];

    /** "Contains" text: key => [column, label]. */
    public const TEXTS = [
        'notes' => ['i.notes', 'Notes'],
    ];

    /** From / to dates, stored as <key>_from and <key>_to: key => [column, label, 'date' or 'datetime' column type]. */
    public const DATES = [
        'due'     => ['i.due_date', 'Due', 'date'],
        'created' => ['i.created_date', 'Added', 'datetime'],
    ];

    private const MAX_TEXT = 100;

    /** @var array<string, string|int|list<string>> only the filters that are in use */
    private array $values = [];

    private function __construct()
    {
    }

    /** @param array<mixed> $raw the ?f[...] part of the address, exactly as received */
    public static function fromQuery(array $raw): self
    {
        $f = new self();

        foreach (self::PICKS as $key => $spec) {
            if (!isset($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }
            $kept = [];
            foreach (array_slice($raw[$key], 0, 50) as $v) {
                if (is_string($v) && isset(ItemsModule::CATEGORIES[$v])) {
                    $kept[$v] = $v;
                }
            }
            if ($kept !== []) {
                $f->values[$key] = array_values($kept);
            }
        }

        foreach (self::TEXTS as $key => $spec) {
            $s = isset($raw[$key]) && is_string($raw[$key]) ? trim(mb_substr($raw[$key], 0, self::MAX_TEXT)) : '';
            if ($s !== '') {
                $f->values[$key] = $s;
            }
        }

        foreach (['qty_min', 'qty_max'] as $key) {
            $n = isset($raw[$key]) && is_string($raw[$key]) ? trim($raw[$key]) : '';
            if (preg_match('/^\d{1,9}$/', $n) === 1) {
                $f->values[$key] = (string) (int) $n;
            }
        }

        foreach (self::DATES as $key => $spec) {
            foreach (['_from', '_to'] as $end) {
                $d = self::date($raw[$key . $end] ?? null);
                if ($d !== '') {
                    $f->values[$key . $end] = $d;
                }
            }
        }

        return $f;
    }

    /** A valid YYYY-MM-DD, or ''. */
    private static function date(mixed $v): string
    {
        if (!is_string($v)) {
            return '';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($v));
        return $d !== false && $d->format('Y-m-d') === trim($v) ? $d->format('Y-m-d') : '';
    }

    /** The value of one filter as the form shows it: a list for pick fields, '' when it is not in use. */
    public function value(string $key): string|array
    {
        return $this->values[$key] ?? (isset(self::PICKS[$key]) ? [] : '');
    }

    /** How many filters are in use (a range counts once). */
    public function count(): int
    {
        return count($this->chips([]));
    }

    /** The ?f[...] part for links, or nothing when no filter is in use. @return array<string, array<string, mixed>> */
    public function query(): array
    {
        return $this->values === [] ? [] : ['f' => $this->values];
    }

    /** @param list<string> $keys @return array<string, array<string, mixed>> the same, without these filters */
    private function queryWithout(array $keys): array
    {
        $rest = array_diff_key($this->values, array_flip($keys));
        return $rest === [] ? [] : ['f' => $rest];
    }

    /**
     * One removable chip per filter in use.
     *
     * @param array<string, array<string, string>> $names labels for picked values, by filter key
     * @return list<array{label:string, query:array<string, array<string, mixed>>}> "query" is the filters left when it is removed
     */
    public function chips(array $names): array
    {
        $chips = [];
        $add = function (string $label, array $keys) use (&$chips): void {
            $chips[] = ['label' => $label, 'query' => $this->queryWithout($keys)];
        };

        foreach (self::PICKS as $key => [, $label]) {
            if (isset($this->values[$key])) {
                $shown = array_map(static fn($v): string => (string) ($names[$key][$v] ?? $v), $this->values[$key]);
                $add($label . ': ' . implode(', ', $shown), [$key]);
            }
        }
        foreach (self::TEXTS as $key => [, $label]) {
            if (isset($this->values[$key])) {
                $add($label . ' contains “' . $this->values[$key] . '”', [$key]);
            }
        }
        $min = $this->values['qty_min'] ?? null;
        $max = $this->values['qty_max'] ?? null;
        if ($min !== null || $max !== null) {
            $add('Quantity: ' . ($min !== null && $max !== null ? "{$min} to {$max}" : ($min !== null ? "{$min} or more" : "{$max} or less")), ['qty_min', 'qty_max']);
        }
        foreach (self::DATES as $key => [, $label]) {
            $from = $this->values[$key . '_from'] ?? null;
            $to = $this->values[$key . '_to'] ?? null;
            if ($from !== null || $to !== null) {
                $add($label . ': ' . ($from !== null && $to !== null ? "{$from} to {$to}" : ($from !== null ? "from {$from}" : "until {$to}")), [$key . '_from', $key . '_to']);
            }
        }
        return $chips;
    }

    /**
     * The filters as SQL to append to a WHERE clause, over the items table aliased "i".
     *
     * @return array{0:string, 1:list<mixed>} [" AND ..." or "", parameters]
     */
    public function where(): array
    {
        if ($this->values === []) {
            return ['', []];
        }
        $sql = [];
        $params = [];

        foreach (self::PICKS as $key => [$column]) {
            if (isset($this->values[$key])) {
                $sql[] = $column . ' IN (' . implode(',', array_fill(0, count($this->values[$key]), '?')) . ')';
                array_push($params, ...$this->values[$key]);
            }
        }

        foreach (self::TEXTS as $key => [$column]) {
            if (isset($this->values[$key])) {
                // '!' is the LIKE escape character so a typed % or _ is matched literally.
                $sql[] = $column . " LIKE ? ESCAPE '!'";
                $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $this->values[$key]) . '%';
            }
        }

        if (isset($this->values['qty_min'])) {
            $sql[] = 'i.quantity >= ?';
            $params[] = (int) $this->values['qty_min'];
        }
        if (isset($this->values['qty_max'])) {
            $sql[] = 'i.quantity <= ?';
            $params[] = (int) $this->values['qty_max'];
        }

        foreach (self::DATES as $key => [$column, , $type]) {
            if (isset($this->values[$key . '_from'])) {
                $sql[] = $column . ' >= ?';
                $params[] = $this->values[$key . '_from'] . ($type === 'datetime' ? ' 00:00:00' : '');
            }
            if (isset($this->values[$key . '_to'])) { // up to and including that whole day
                if ($type === 'datetime') {
                    $sql[] = $column . ' < ?';
                    $params[] = (new \DateTimeImmutable((string) $this->values[$key . '_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
                } else {
                    $sql[] = $column . ' <= ?';
                    $params[] = $this->values[$key . '_to'];
                }
            }
        }

        return [' AND ' . implode(' AND ', $sql), $params];
    }
}
