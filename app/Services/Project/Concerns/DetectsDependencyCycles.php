<?php

declare(strict_types=1);

namespace App\Services\Project\Concerns;

use App\Exceptions\Project\DependencyCycleException;

/**
 * Bagimlilik grafinda dongu kontrolu (11 SS1.6 "dongu kontrolu uygulama
 * servisinde"). Yeni kenar predecessor -> successor eklenirken successor'dan
 * ileri dogru yuruyup predecessor'a ulasiliyorsa dongu vardir.
 *
 * @param  array<int, list<int>>  $edges  predecessor => successor listesi
 */
trait DetectsDependencyCycles
{
    /**
     * @param  array<int, list<int>>  $edges
     */
    protected function assertNoCycle(array $edges, int $predecessor, int $successor): void
    {
        if ($predecessor === $successor) {
            throw DependencyCycleException::make();
        }

        $stack = [$successor];
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $predecessor) {
                throw DependencyCycleException::make();
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            foreach ($edges[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }
    }
}
