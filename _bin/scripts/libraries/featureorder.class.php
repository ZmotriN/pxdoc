<?php
declare(strict_types=1);

/**
 * FeatureOrderMaxLA (object-safe)
 * - Accepte un GeoJSON décodé en objets (stdClass) OU en tableaux associatifs.
 * - Construit l’adjacence (touches coin ou côté) entre Polygon/MultiPolygon.
 * - Calcule un ordre de features maximisant ∑ |pos(u)-pos(v)| (Max Linear Arrangement).
 * - Retourne un tableau d'IDs (strings) dans l’ordre trouvé.
 */
final class FeatureOrder
{
    /**
     * @param array|object $geojson   FeatureCollection (array|stdClass)
     * @param string|null  $idKey     Clé d'ID à utiliser (ex: 'id','IDUGD'). Null = auto-détection.
     * @param array        $idFallbacks Clés alternatives si $idKey absente.
     * @param float        $epsilon   Tolérance géométrique (sommets/colinéarité).
     * @param int          $iterations Nombre d’itérations d’optimisation (échanges).
     * @param bool         $includeCornerTouch true: coin-à-coin = voisins; false: ignorer coins.
     * @return array<string>
     */
    public static function orderIds(
        $geojson,
        ?string $idKey = null,
        array $idFallbacks = ['id','ID','Id','section','Section','name','Name','IDUGD','ADIDU'],
        float $epsilon = 1e-7,
        int $iterations = 80000,
        bool $includeCornerTouch = true
    ): array {
        $features = self::aget($geojson, 'features');
        if (!is_array($features)) $features = []; // sécurité

        $n = count($features);
        if ($n === 0) return [];

        // --- Anneaux + bbox par feature ---
        $rings  = [];
        $bboxes = [];
        for ($i = 0; $i < $n; $i++) {
            $geom = self::aget($features[$i], 'geometry');
            [$r, $bb] = self::extractRingsAndBBox($geom);
            $rings[$i]  = $r;
            $bboxes[$i] = $bb;
        }

        // --- Graphe d'adjacence ---
        $adj = array_fill(0, $n, array_fill(0, $n, false));
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (!self::bboxIntersect($bboxes[$i], $bboxes[$j])) continue;
                if (self::areAdjacent($rings[$i], $rings[$j], $epsilon, $includeCornerTouch)) {
                    $adj[$i][$j] = $adj[$j][$i] = true;
                }
            }
        }
        $edges = [];
        $nbrs  = array_fill(0, $n, []);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($adj[$i][$j]) {
                    $edges[] = [$i, $j];
                    $nbrs[$i][] = $j;
                    $nbrs[$j][] = $i;
                }
            }
        }

        // --- Max Linear Arrangement (permutation) ---
        $order = range(0, $n - 1);
        usort($order, fn($a, $b) => count($nbrs[$a]) <=> count($nbrs[$b])); // démarrage: faible degré aux extrémités
        $pos = array_fill(0, $n, 0);
        for ($k = 0; $k < $n; $k++) $pos[$order[$k]] = $k;

        $T    = max(1.0, self::maxDegree($nbrs));
        $cool = 0.995;

        // mt_srand(123); // décommente pour reproductibilité

        for ($t = 0; $t < $iterations; $t++) {
            $uIdx = random_int(0, $n - 1);
            $vIdx = random_int(0, $n - 1);
            if ($uIdx === $vIdx) continue;

            $u = $order[$uIdx];
            $v = $order[$vIdx];

            $delta = self::deltaSwap($edges, $pos, $nbrs, $u, $v);
            if ($delta > 0.0 || (mt_rand() / mt_getrandmax()) < exp($delta / max($T, 1e-12))) {
                // on échange
                $order[$uIdx] = $v;
                $order[$vIdx] = $u;
                $pos[$u] = $vIdx;
                $pos[$v] = $uIdx;
            }
            $T *= $cool;
            if ($T < 1e-6) $T = 1e-6;
        }

        // --- IDs dans l'ordre ---
        $chosenKey = $idKey ?? self::autoPickIdKey($features, $idFallbacks);
        $ids = [];
        for ($k = 0; $k < $n; $k++) {
            $node = $order[$k];
            $ids[] = self::featureId($features[$k], $features[$node], $chosenKey, $idFallbacks);
        }

        return $ids;
    }

    // ======================= Helpers génériques array/objet =======================

    /** Accès clé 'k' sur array|object; null si absent */
    private static function aget($v, string $k) {
        if (is_array($v))  return $v[$k]  ?? null;
        if (is_object($v)) return $v->$k ?? null;
        return null;
    }

    // ======================= IDs =======================

    private static function autoPickIdKey(array $features, array $candidates): ?string
    {
        $best = null; $bestPresent = -1; $bestUnique = -1;
        foreach ($candidates as $k) {
            $vals = [];
            $present = 0;
            foreach ($features as $f) {
                $props = self::aget($f, 'properties');
                $v = self::aget($props, $k);
                if ($v !== null && $v !== '') { $present++; $vals[] = (string)$v; }
            }
            $uniq = count(array_unique($vals));
            if ($present > $bestPresent || ($present === $bestPresent && $uniq > $bestUnique)) {
                $bestPresent = $present; $bestUnique = $uniq; $best = $k;
            }
        }
        return $best ?? 'id';
    }

    /**
     * Retourne l'ID (string) du feature $nodeFeature (ordre donné par $order),
     * en utilisant $idKey puis $fallbacks; sinon index en string.
     */
    private static function featureId($originalFeature, $nodeFeature, ?string $idKey, array $fallbacks): string
    {
        // On lit l’ID à partir du feature positionné ($nodeFeature)
        $props = self::aget($nodeFeature, 'properties');

        if ($idKey !== null) {
            $v = self::aget($props, $idKey);
            if ($v !== null && $v !== '') return (string)$v;
        }
        foreach ($fallbacks as $k) {
            $v = self::aget($props, $k);
            if ($v !== null && $v !== '') return (string)$v;
        }
        // GeoJSON-level id
        $fid = self::aget($nodeFeature, 'id');
        if ($fid !== null && $fid !== '') return (string)$fid;

        // fallback: index (de l’original, peu importe ici → on renvoie une string)
        return (string)spl_object_id((object)$nodeFeature);
    }

    // ======================= Géométrie / Adjacence =======================

    /** @return array{0: array<int,array<int,array{float,float}>>, 1: ?array{float,float,float,float}} */
    private static function extractRingsAndBBox($geom): array
    {
        $type = self::aget($geom, 'type');
        $coords = self::aget($geom, 'coordinates');
        $rings = [];

        if ($type === 'Polygon' && is_array($coords)) {
            foreach ($coords as $ring) $rings[] = self::normalizeRing($ring);
        } elseif ($type === 'MultiPolygon' && is_array($coords)) {
            foreach ($coords as $poly) {
                if (!is_array($poly)) continue;
                foreach ($poly as $ring) $rings[] = self::normalizeRing($ring);
            }
        }

        $bbox = self::bboxOfRings($rings);
        return [$rings, $bbox];
    }

    /** @param array<int,array<int,float|int>>|mixed $ring */
    private static function normalizeRing($ring): array
    {
        $out = [];
        if (is_array($ring)) {
            foreach ($ring as $pt) {
                if (is_array($pt) && count($pt) >= 2) {
                    $x = (float)$pt[0]; $y = (float)$pt[1];
                    $out[] = [$x, $y];
                }
            }
        }
        $n = count($out);
        if ($n > 0) {
            $a = $out[0]; $b = $out[$n - 1];
            if ($a[0] !== $b[0] || $a[1] !== $b[1]) $out[] = $a; // fermer
        }
        return $out;
    }

    private static function bboxOfRings(array $rings): ?array
    {
        $minx = $miny = INF; $maxx = $maxy = -INF;
        foreach ($rings as $ring) {
            foreach ($ring as [$x, $y]) {
                if ($x < $minx) $minx = $x; if ($x > $maxx) $maxx = $x;
                if ($y < $miny) $miny = $y; if ($y > $maxy) $maxy = $y;
            }
        }
        return is_finite($minx) ? [$minx, $miny, $maxx, $maxy] : null;
    }

    private static function bboxIntersect(?array $a, ?array $b): bool
    {
        if (!$a || !$b) return false;
        return !($a[2] < $b[0] || $a[0] > $b[2] || $a[3] < $b[1] || $a[1] > $b[3]);
    }

    private static function areAdjacent(array $ringsA, array $ringsB, float $eps, bool $includeCornerTouch): bool
    {
        if ($includeCornerTouch) {
            // Partage d’un sommet (coin-à-coin)
            $seen = [];
            foreach ($ringsA as $ra) foreach ($ra as [$x,$y]) {
                $seen[self::qKey($x,$y,$eps)] = true;
            }
            foreach ($ringsB as $rb) foreach ($rb as [$x,$y]) {
                if (isset($seen[self::qKey($x,$y,$eps)])) return true;
            }
        }

        // Segments qui se touchent / croisent
        foreach ($ringsA as $ra) {
            for ($i = 0; $i + 1 < count($ra); $i++) {
                [$ax1,$ay1] = $ra[$i];
                [$ax2,$ay2] = $ra[$i+1];
                foreach ($ringsB as $rb) {
                    for ($j = 0; $j + 1 < count($rb); $j++) {
                        [$bx1,$by1] = $rb[$j];
                        [$bx2,$by2] = $rb[$j+1];
                        if (self::segmentsTouchOrCross($ax1,$ay1,$ax2,$ay2,$bx1,$by1,$bx2,$by2,$eps)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    private static function qKey(float $x, float $y, float $eps): string
    {
        return (string)((int)round($x/$eps)) . ':' . (string)((int)round($y/$eps));
    }

    private static function segmentsTouchOrCross(
        float $x1,float $y1,float $x2,float $y2,
        float $x3,float $y3,float $x4,float $y4,
        float $eps
    ): bool {
        $o1 = self::orient($x1,$y1,$x2,$y2,$x3,$y3,$eps);
        $o2 = self::orient($x1,$y1,$x2,$y2,$x4,$y4,$eps);
        $o3 = self::orient($x3,$y3,$x4,$y4,$x1,$y1,$eps);
        $o4 = self::orient($x3,$y3,$x4,$y4,$x2,$y2,$eps);

        if ($o1*$o2 < 0 && $o3*$o4 < 0) return true; // croisés

        // colinéarité + appartenance au segment
        if ($o1 === 0 && self::onSeg($x1,$y1,$x2,$y2,$x3,$y3,$eps)) return true;
        if ($o2 === 0 && self::onSeg($x1,$y1,$x2,$y2,$x4,$y4,$eps)) return true;
        if ($o3 === 0 && self::onSeg($x3,$y3,$x4,$y4,$x1,$y1,$eps)) return true;
        if ($o4 === 0 && self::onSeg($x3,$y3,$x4,$y4,$x2,$y2,$eps)) return true;

        return false;
    }

    private static function orient(float $ax,float $ay,float $bx,float $by,float $cx,float $cy,float $eps): int
    {
        $v = ($bx - $ax)*($cy - $ay) - ($by - $ay)*($cx - $ax);
        if (abs($v) <= $eps) return 0;
        return ($v > 0) ? 1 : -1;
    }

    private static function onSeg(float $ax,float $ay,float $bx,float $by,float $px,float $py,float $eps): bool
    {
        $minx = min($ax,$bx) - $eps; $maxx = max($ax,$bx) + $eps;
        $miny = min($ay,$by) - $eps; $maxy = max($ay,$by) + $eps;
        if ($px < $minx || $px > $maxx || $py < $miny || $py > $maxy) return false;

        $dx = $bx - $ax; $dy = $by - $ay;
        $len2 = $dx*$dx + $dy*$dy;
        if ($len2 == 0.0) {
            return (($px - $ax)*($px - $ax) + ($py - $ay)*($py - $ay)) <= ($eps*$eps);
        }
        $area2 = abs(($px - $ax)*$dy - ($py - $ay)*$dx);
        $dist  = $area2 / sqrt($len2);
        return $dist <= $eps;
    }

    private static function maxDegree(array $nbrs): int
    {
        $m = 0;
        foreach ($nbrs as $nei) $m = max($m, count($nei));
        return $m;
    }

    private static function deltaSwap(array $edges, array $pos, array $nbrs, int $u, int $v): float
    {
        $pu = $pos[$u]; $pv = $pos[$v];
        if ($pu === $pv) return 0.0;

        $delta = 0.0;
        foreach ($nbrs[$u] as $w) {
            if ($w === $v) continue;
            $delta -= abs($pu - $pos[$w]);
            $delta += abs($pv - $pos[$w]);
        }
        foreach ($nbrs[$v] as $w) {
            if ($w === $u) continue;
            $delta -= abs($pv - $pos[$w]);
            $delta += abs($pu - $pos[$w]);
        }
        // l’arête (u,v) ne change pas: |pu-pv| == |pv-pu|
        return $delta;
    }
}
