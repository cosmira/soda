<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\ProjectFacts;

/**
 * Resolve usage across configured files using compact, receiver-aware declarations.
 * No reachability analysis or dependency autoloading is performed.
 */
final class UnusedMethodAnalyser
{
    use UnusedMethodComposition;
    use UnusedMethodResolution;

    /**
     * @var array<string, array>
     */
    private array $types = [];

    /**
     * @var array<string, array>
     */
    private array $surfaces = [];

    /**
     * @var array<string, true>
     */
    private array $used = [];

    /**
     * Index once, resolve possible calls, then report each original declaration once.
     *
     * @return list<array{file: string, class: string, method: string, visibility: string, line: int}>
     */
    public function analyse(ProjectFacts $project): array
    {
        $this->types = $this->index($project);
        $this->surfaces = [];
        $this->used = [];
        $candidates = [];
        foreach ($this->types as $name => $type) {
            if ($type['kind'] === 'trait') {
                continue;
            }

            $surface = $this->surface($name);
            $candidates += $this->candidates($surface);
            $this->calls($name, $surface);
        }

        return array_values(array_diff_key($candidates, $this->used));
    }

    /**
     * Anonymous identities include their file; PHP type and method lookup ignores case.
     */
    private function index(ProjectFacts $project): array
    {
        $types = [];
        foreach ($project->files as $file => $facts) {
            foreach ($facts['methodUsage'] ?? [] as $type) {
                $name = strtolower($type['name']);
                if (str_starts_with($name, '{anonymous}')) {
                    $name .= '@'.$file;
                }

                $type['duplicate'] = isset($types[$name]);
                $types[$name] = $this->locate($type, $name, $file);
            }
        }

        return $types;
    }

    /**
     * Origin survives trait aliases, inheritance and every possible consuming class.
     */
    private function locate(array $type, string $name, string $file): array
    {
        $methods = $type['methods'];
        foreach ($methods as $key => $method) {
            $methods[$key] = $method + [
                'origin' => $file.':'.$method['line'].':'.$key,
                'scope'  => $name, 'class' => $type['name'], 'file' => $file,
            ];
        }

        $type['methods'] = $methods;

        return $type;
    }

    /**
     * Candidate policy is unchanged; unresolved compositions cannot prove non-use.
     */
    private function candidates(array $surface): array
    {
        $candidates = [];
        foreach ($surface['bodies'] as $method) {
            if ($surface['unknown'] || $method['visibility'] === 'public') {
                $this->used[$method['origin']] = true;
            }

            $isCandidate = $method['candidate'] && ! $this->hasContract($method);
            if ($isCandidate) {
                $candidates[$method['origin']] = [
                    'file'   => $method['file'], 'class' => $method['class'],
                    'method' => $method['name'], 'visibility' => $method['visibility'], 'line' => $method['line'],
                ];
            }
        }

        return $candidates;
    }

    /**
     * A protected override retains the existing abstract/interface contract exemption.
     */
    private function hasContract(array $method): bool
    {
        if ($method['visibility'] !== 'protected') {
            return false;
        }

        $type = $this->types[$method['scope']];
        $ancestors = [...$type['interfaces'], ...($type['parent'] === null ? [] : [$type['parent']])];

        return $this->hasNamedContract($ancestors, strtolower($method['name']));
    }

    /**
     * Missing contracts remain unknown; no vendor path is inspected implicitly.
     */
    private function hasNamedContract(array $names, string $method, array $seen = []): bool
    {
        foreach ($names as $name) {
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $type = $this->types[$name] ?? null;
            if ($type === null) {
                return true;
            }

            $methods = $type['methods'];
            if ($type['abstract'] && isset($methods[$method])) {
                return true;
            }

            $parents = [...$type['interfaces'], ...($type['parent'] === null ? [] : [$type['parent']])];
            if ($this->hasNamedContract($parents, $method, $seen)) {
                return true;
            }
        }

        return false;
    }
}
