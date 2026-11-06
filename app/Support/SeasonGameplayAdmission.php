<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/** Maintenance is a distinct confirmed transaction before admission to gameplay. */
final class SeasonGameplayAdmission
{
    public static function prepare(PDO $db, callable $finalize, array &$maintenance): array
    {
        SeasonLifecycleLock::assertHeld($db);
        // Bound re-evaluation if a season expires while maintenance is running.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $now = (string) $db->query('SELECT NOW(6)')->fetchColumn();
            $stmt = $db->prepare("SELECT *, starts_at <= ? AS has_started, ends_at <= ? AS has_ended
                FROM seasons WHERE status = 'active' ORDER BY id");
            $stmt->execute([$now, $now]);
            $active = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $expired = [];
            $eligible = [];
            foreach ($active as $season) {
                if (!isset($season['starts_at'], $season['ends_at'])
                    || $season['starts_at'] >= $season['ends_at']) {
                    throw new \RuntimeException('Invalid active season boundaries.');
                }
                if ((int) $season['has_ended'] === 1) { $expired[] = $season; }
                elseif ((int) $season['has_started'] !== 1) {
                    throw new \RuntimeException('Future active schedule requires review.');
                } else { $eligible[] = $season; }
            }
            if (count($eligible) > 1) { throw new \RuntimeException('Ambiguous season eligibility.'); }
            if ($expired === [] && count($eligible) === 1) {
                return ['admitted_at' => $now, 'season' => $eligible[0]];
            }
            $stage = 'begin';
            $pending = [];
            try {
                if (!$db->beginTransaction()) { throw new \RuntimeException('Maintenance begin not acknowledged.'); }
                $stage = 'operation';
                GameplayOperationContext::run($db, function () use ($db, $expired, $eligible, $finalize, &$pending): void {
                    foreach ($expired as $season) {
                        $finalize($db, $season);
                        $db->prepare("UPDATE seasons SET status = 'ended' WHERE id = ?")->execute([(int) $season['id']]);
                        $pending[] = (int) $season['id'];
                    }
                    if ($eligible === []) {
                        $db->prepare("INSERT INTO seasons
                            (name, slug, starts_at, ends_at, status, weight_pvp, weight_boss, weight_cultivation, weight_sect)
                            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 28 DAY), 'active', 1, 0.01, 1, 5)")
                            ->execute(['Season '.gmdate('Y-m-d'), 'season-'.bin2hex(random_bytes(12))]);
                    }
                });
                $stage = 'commit';
                if (!$db->commit()) { throw new \RuntimeException('Maintenance commit not acknowledged.'); }
                $maintenance['committed'] = true;
                $maintenance['finalized'] = array_merge($maintenance['finalized'], $pending);
                $maintenance['optional_effect_failures'] = array_merge($maintenance['optional_effect_failures'], GameplayOperationContext::effects(true));
            } catch (Throwable $error) {
                GameplayOperationContext::effects(false);
                if (in_array($stage, ['begin', 'commit'], true)) { $maintenance['committed'] = null; }
                try {
                    if ($db->inTransaction() && (!$db->rollBack() || $db->inTransaction())) {
                        throw new \RuntimeException('Maintenance rollback not acknowledged.');
                    }
                } catch (Throwable $rollback) {
                    $maintenance['committed'] = null;
                    SeasonLifecycleLock::quarantine($db);
                    throw new SeasonLifecycleLockException('maintenance_rollback_uncertain', $error, true, ['rollback' => $rollback]);
                }
                if (in_array($stage, ['begin', 'commit'], true)) {
                    SeasonLifecycleLock::quarantine($db);
                    throw new SeasonLifecycleLockException('maintenance_'.$stage.'_uncertain', $error, true);
                }
                throw $error;
            }
        }
        throw new \RuntimeException('Season admission re-evaluation limit reached.');
    }
}
