<?php
declare(strict_types=1);

/**
 * FeatureOrder v2 — évite les voisins aux teintes trop proches.
 *
 * Usage:
 *   $ids = FeatureOrder::orderIds($geojson, [
 *     'idKeys' => ['id','code','fid','name'],
 *     'avoidNeighbors' => true,   // par défaut true
 *     'paletteSize' => 16,        // = count($palette)
 *   ]);
 */
final class FeatureOrder
{
    /**
     * $geojson : chemin .geojson, string JSON, ou array décodé.
     * $options :
     *  - idKeys        : string[]
     *  - seed          : 'barycenter'|'pair' (fallback farthest-first)
     *  - metric        : 'haversine'|'euclid' (fallback)
     *  - step          : int|null (fallback, permutation stride)
     *  - avoidNeighbors: bool (DSATUR ON)             [def: true]
     *  - paletteSize   : int  (nb couleurs palette)   [def: 16]
     *  - adjacency     : 'bbox'|'none'                [def: 'bbox']
     *  - bboxEps       : float marge BBox             [def: 1e-12]
     */
    public static function orderIds($geojson, array $options = []): array
    {
        $idKeys        = $options['idKeys']        ?? ['id','code','ID','fid','name'];
        $seed          = $options['seed']          ?? 'barycenter';
        $metric        = $options['metric']        ?? 'haversine';
        $step          = $options['step']          ?? null;
        $avoidNeighbors= $options['avoidNeighbors']?? true;
        $paletteSize   = max(1, (int)($options['paletteSize'] ?? 16));
        $adjacencyMode = $options['adjacency']     ?? 'bbox';
        $bboxEps       = (float)($options['bboxEps'] ?? 1e-12);

        $fc    = self::loadFeatureCollection($geojson);
        $items = self::extractItems($fc, $idKeys);
        $n = count($items);
        if ($n === 0) return [];
        if ($n === 1) return [$items[0]['id']];

        if ($avoidNeighbors && $adjacencyMode !== 'none') {
            // 1) Adjacence par BBox
            $neighbors  = self::neighborsFromBBoxes($items, $bboxEps);
            // 2) Coloration DSATUR (indices 0..paletteSize-1)
            $colorIndex = self::dsaturColoring($neighbors, $paletteSize);
            // 3) Intercalage round-robin par classe de couleur pour respecter i % paletteSize
            $ordered    = self::roundRobinByColor($colorIndex, $items);
            return array_map(fn($it) => $it['id'], $ordered);
        }

        // Fallback historique: farthest-first + permutation stride
        $ffIdx = self::farthestFirstOrder($items, $metric, $seed);
        $perm  = self::spreadIndices($n, $step);
        $out   = array_fill(0, $n, null);
        foreach ($ffIdx as $rank => $idx) {
            $pos = $perm[$rank];
            $out[$pos] = $items[$idx]['id'];
        }
        return $out;
    }

    /* ---------------- Parsing / Extraction ---------------- */

    private static function loadFeatureCollection($src): array
    {
        if (is_array($src)) {
            $fc = $src;
        } elseif (is_string($src)) {
            $json = is_file($src) ? file_get_contents($src) : $src;
            $fc   = json_decode($json, true);
            if (!is_array($fc)) throw new \RuntimeException('GeoJSON invalide');
        } else {
            throw new \InvalidArgumentException('GeoJSON: type non supporté');
        }
        if (($fc['type'] ?? null) !== 'FeatureCollection') {
            throw new \InvalidArgumentException('Attendu FeatureCollection');
        }
        return $fc;
    }

    private static function extractItems(array $fc, array $idKeys): array
    {
        $items = [];
        foreach ($fc['features'] ?? [] as $f) {
            $id = null;
            if (isset($f['id'])) $id = (string)$f['id'];
            if ($id === null && isset($f['properties']) && is_array($f['properties'])) {
                foreach ($idKeys as $k) {
                    if (array_key_exists($k, $f['properties'])) { $id = (string)$f['properties'][$k]; break; }
                }
            }
            if ($id === null) continue;

            $geom = $f['geometry'] ?? null;
            if (!is_array($geom)) continue;
            [$cx,$cy] = self::centroid($geom);
            $bbox      = self::bbox($geom);

            $items[] = ['id'=>$id, 'centroid'=>[$cx,$cy], 'bbox'=>$bbox];
        }
        return $items;
    }

    /* ---------------- Géométrie ---------------- */

    private static function centroid(array $geom): array
    {
        $t = $geom['type'] ?? null;
        $c = $geom['coordinates'] ?? null;
        if ($t === 'Polygon') {
            return self::polygonCentroid($c);
        } elseif ($t === 'MultiPolygon') {
            $sumA = 0.0; $sx = 0.0; $sy = 0.0;
            foreach ($c as $poly) {
                [$x,$y,$A] = self::polygonCentroidWithArea($poly);
                if ($A !== 0.0) { $sumA += $A; $sx += $x*$A; $sy += $y*$A; }
            }
            if ($sumA !== 0.0) return [$sx/$sumA, $sy/$sumA];
            $xs=0;$ys=0;$n=0; foreach ($c as $poly){[$x,$y]=self::polygonCentroid($poly);$xs+=$x;$ys+=$y;$n++;}
            return [$xs/$n, $ys/$n];
        } elseif ($t === 'Point') {
            return [$c[0], $c[1]];
        } else {
            $b = self::bbox(['type'=>$t,'coordinates'=>$c]);
            return [($b[0]+$b[2])/2, ($b[1]+$b[3])/2];
        }
    }

    private static function polygonCentroid(array $polygon): array
    {
        [$x,$y,] = self::polygonCentroidWithArea($polygon);
        return [$x,$y];
    }

    private static function polygonCentroidWithArea(array $polygon): array
    {
        $ring = $polygon[0] ?? [];
        $A = 0.0; $cx = 0.0; $cy = 0.0;
        $n = count($ring);
        if ($n < 3) return [$ring[0][0] ?? 0.0, $ring[0][1] ?? 0.0, 0.0];
        for ($i=0,$j=$n-1; $i<$n; $j=$i, $i++) {
            $xi=$ring[$i][0]; $yi=$ring[$i][1];
            $xj=$ring[$j][0]; $yj=$ring[$j][1];
            $cross = $xj*$yi - $xi*$yj;
            $A  += $cross;
            $cx += ($xj + $xi) * $cross;
            $cy += ($yj + $yi) * $cross;
        }
        $A *= 0.5;
        if ($A == 0.0) return [$ring[0][0], $ring[0][1], 0.0];
        $cx /= (6.0*$A);
        $cy /= (6.0*$A);
        return [$cx,$cy,$A];
    }

    private static function bbox(array $geom): array
    {
        $coords = $geom['coordinates'] ?? [];
        $stack = [$coords];
        $minx=INF;$miny=INF;$maxx=-INF;$maxy=-INF;
        while ($stack) {
            $v = array_pop($stack);
            if (!is_array($v)) continue;
            if (is_array($v) && isset($v[0]) && is_numeric($v[0]) && isset($v[1]) && is_numeric($v[1])) {
                $x=$v[0]; $y=$v[1];
                if ($x<$minx) $minx=$x; if ($x>$maxx) $maxx=$x;
                if ($y<$miny) $miny=$y; if ($y>$maxy) $maxy=$y;
            } else {
                foreach ($v as $w) $stack[] = $w;
            }
        }
        if ($minx===INF) return [0,0,0,0];
        return [$minx,$miny,$maxx,$maxy];
    }

    /* ---------------- Distances & farthest-first (fallback) ---------------- */

    private static function dist(array $aItem, array $bItem, string $metric): float
    {
        [$ax,$ay] = $aItem['centroid'];
        [$bx,$by] = $bItem['centroid'];
        if ($metric === 'euclid') {
            $dx = $ax - $bx; $dy = $ay - $by;
            return sqrt($dx*$dx + $dy*$dy);
        }
        $R = 6371000.0;
        $lat1 = deg2rad($ay); $lat2 = deg2rad($by);
        $dlat = $lat2 - $lat1;
        $dlon = deg2rad($bx - $ax);
        $s = sin($dlat/2)**2 + cos($lat1)*cos($lat2)*sin($dlon/2)**2;
        return 2*$R*asin(min(1,sqrt($s)));
    }

    private static function farthestFirstOrder(array $items, string $metric, string $seed): array
    {
        $n = count($items);
        if ($seed === 'pair') {
            $bestA = 0; $bestB = 1; $bestD = -1.0;
            for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
                $d = self::dist($items[$i], $items[$j], $metric);
                if ($d > $bestD) { $bestD = $d; $bestA=$i; $bestB=$j; }
            }
            $order = [$bestA, $bestB];
        } else {
            $sx=0.0;$sy=0.0;
            foreach ($items as $it) { $sx += $it['centroid'][0]; $sy += $it['centroid'][1]; }
            $cx=$sx/count($items); $cy=$sy/count($items);
            $best=0; $bestD=-1.0;
            foreach ($items as $i=>$it) {
                $d = ($metric==='euclid')
                    ? hypot($it['centroid'][0]-$cx, $it['centroid'][1]-$cy)
                    : self::dist(['centroid'=>[$cx,$cy]], $it, $metric);
                if ($d > $bestD) { $bestD = $d; $best = $i; }
            }
            $order = [$best];
        }

        $selected = array_fill(0, $n, false);
        foreach ($order as $i) $selected[$i] = true;

        $minDist = array_fill(0, $n, INF);
        foreach ($order as $i) {
            for ($j = 0; $j < $n; $j++) {
                if ($selected[$j]) continue;
                $d = self::dist($items[$i], $items[$j], $metric);
                if ($d < $minDist[$j]) $minDist[$j] = $d;
            }
        }

        while (count($order) < $n) {
            $next = -1; $best = -1.0;
            for ($i = 0; $i < $n; $i++) {
                if ($selected[$i]) continue;
                if ($minDist[$i] > $best) { $best = $minDist[$i]; $next = $i; }
            }
            if ($next === -1) break;

            $selected[$next] = true;
            $order[] = $next;

            for ($i = 0; $i < $n; $i++) {
                if ($selected[$i]) continue;
                $d = self::dist($items[$i], $items[$next], $metric);
                if ($d < $minDist[$i]) $minDist[$i] = $d;
            }
        }

        return $order;
    }

    private static function spreadIndices(int $n, ?int $step = null): array
    {
        if ($n <= 1) return [0];
        if ($step === null) {
            $phi  = (1 + sqrt(5)) / 2;
            $step = max(1, (int)round($n / $phi));
        }
        $g = self::gcd($n, $step);
        if ($g !== 1) {
            for ($d = 1; $d < $n; $d++) {
                if (self::gcd($n, $step+$d) === 1) { $step += $d; break; }
                if ($step-$d > 0 && self::gcd($n, $step-$d) === 1) { $step -= $d; break; }
            }
        }
        $i = 0; $perm = [];
        for ($k=0;$k<$n;$k++) { $perm[] = $i; $i = ($i + $step) % $n; }
        return $perm;
    }
    private static function gcd(int $a, int $b): int { return $b ? self::gcd($b, $a % $b) : abs($a); }

    /* ---------------- Adjacence ---------------- */

    private static function neighborsFromBBoxes(array $items, float $eps = 0.0): array
    {
        $n = count($items);
        $adj = array_fill(0, $n, []);
        for ($i=0;$i<$n;$i++) {
            $ai = $items[$i]['bbox'];
            for ($j=$i+1;$j<$n;$j++) {
                $bj = $items[$j]['bbox'];
                if (self::bboxOverlap($ai, $bj, $eps)) {
                    $adj[$i][] = $j; $adj[$j][] = $i;
                }
            }
        }
        return $adj;
    }

    private static function bboxOverlap(array $a, array $b, float $eps = 0.0): bool
    {
        return !($a[2] < $b[0]-$eps || $b[2] < $a[0]-$eps ||
                 $a[3] < $b[1]-$eps || $b[3] < $a[1]-$eps);
    }

    /* ---------------- DSATUR ---------------- */

    private static function dsaturColoring(array $neighbors, int $paletteSize): array
    {
        $n = count($neighbors);
        $color = array_fill(0, $n, -1);
        $degree = array_map(fn($vs) => count(array_unique($vs)), $neighbors);
        $uncolored = range(0, $n-1);

        $ringDist = function (int $a, int $b) use ($paletteSize): int {
            $d = abs($a - $b);
            return min($d, $paletteSize - $d);
        };

        while (!empty($uncolored)) {
            // choisir le sommet à saturation max (tie-break: degré)
            $sat = [];
            foreach ($uncolored as $v) {
                $used = [];
                foreach ($neighbors[$v] as $u) {
                    $cu = $color[$u];
                    if ($cu !== -1) $used[$cu] = true;
                }
                $sat[$v] = count($used);
            }
            $v = null;
            foreach ($uncolored as $cand) {
                if ($v === null) { $v = $cand; continue; }
                if ($sat[$cand] > $sat[$v] ||
                   ($sat[$cand] === $sat[$v] && $degree[$cand] > $degree[$v])) {
                    $v = $cand;
                }
            }

            // couleurs voisines
            $neighborColors = [];
            foreach ($neighbors[$v] as $u) {
                $cu = $color[$u];
                if ($cu !== -1) $neighborColors[$cu] = true;
            }

            // candidats
            $candidates = [];
            for ($c=0;$c<$paletteSize;$c++) if (!isset($neighborColors[$c])) $candidates[] = $c;

            // choisir la couleur qui maximise la distance min aux voisines (anneau)
            $bestC = 0; $bestScore = -1;
            if (!empty($candidates)) {
                foreach ($candidates as $c) {
                    if (empty($neighborColors)) { $bestC = $c; $bestScore = INF; break; }
                    $minD = PHP_INT_MAX;
                    foreach (array_keys($neighborColors) as $nc) {
                        $minD = min($minD, $ringDist($c, $nc));
                    }
                    if ($minD > $bestScore) { $bestScore = $minD; $bestC = $c; }
                }
            } else {
                foreach (range(0, $paletteSize-1) as $c) {
                    $minD = PHP_INT_MAX;
                    foreach (array_keys($neighborColors) as $nc) {
                        $minD = min($minD, $ringDist($c, $nc));
                    }
                    if ($minD > $bestScore) { $bestScore = $minD; $bestC = $c; }
                }
            }

            $color[$v] = $bestC;
            $uncolored = array_values(array_diff($uncolored, [$v]));
        }
        return $color; // index feature -> index couleur [0..paletteSize-1]
    }

    /**
     * Place les features pour que (position % paletteSize) == colorIndex[feature].
     * Round-robin 0..N-1, 0..N-1 en prenant 1 élément de chaque classe si dispo.
     */
    private static function roundRobinByColor(array $colorIndex, array $items): array
    {
        $n = count($items);
        $N = max(1, ...array_map(fn($c)=>$c+1, $colorIndex));
        $buckets = array_fill(0, $N, []);
        foreach ($items as $i=>$it) {
            $c = $colorIndex[$i];
            if (!isset($buckets[$c])) $buckets[$c] = [];
            $buckets[$c][] = $it;
        }

        // petit tri interne pour des répétitions plus jolies au sein d'une même couleur
        foreach ($buckets as $c => $bucket) {
            if (count($bucket) <= 2) continue;
            usort($bucket, function($a,$b){
                [$ax,$ay] = $a['centroid']; [$bx,$by] = $b['centroid'];
                if ($ax === $bx) return $ay <=> $by;
                return $ax <=> $bx;
            });
            $buckets[$c] = $bucket;
        }

        $out = [];
        $exhausted = false; $k = 0;
        while (!$exhausted) {
            $exhausted = true;
            for ($c=0; $c<$N; $c++) {
                if (!empty($buckets[$c])) {
                    $out[] = array_shift($buckets[$c]);
                    $exhausted = false;
                }
            }
            $k++; if ($k > $n + $N) break; // sécurité
        }
        return $out;
    }
}
