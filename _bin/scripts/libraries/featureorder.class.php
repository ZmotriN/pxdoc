<?php
declare(strict_types=1);

final class FeatureOrder
{
    /**
     * Point d'entrée.
     * $geojson : chemin de fichier .geojson, string JSON, ou array décodé.
     * $options :
     *  - idKeys: string[] ordre des clés possibles pour trouver l'ID (dans feature.id puis properties[...])
     *  - seed: 'barycenter'|'pair' (défaut 'barycenter')
     *  - metric: 'haversine'|'euclid' (défaut 'haversine')
     *  - step: int|null pas copremier pour la permutation (défaut null = auto ~phi)
     */
    public static function orderIds($geojson, array $options = []): array
    {
        $fc = self::asFeatureCollection($geojson);
        if (!isset($fc['features']) || !is_array($fc['features'])) return [];

        $idKeys = $options['idKeys'] ?? ['id','ID','Id','IDUGD','ADIDU','PRIDU','NAME','name','code','Code'];
        $seed   = $options['seed']   ?? 'barycenter';
        $metric = $options['metric'] ?? 'haversine';
        $step   = $options['step']   ?? null;

        // 1) Extraire (id, lat, lng) depuis le GeoJSON
        $items = []; // each: ['id'=>string, 'lat'=>float, 'lng'=>float]
        foreach ($fc['features'] as $idx => $feat) {
            $id = self::extractId($feat, $idKeys);
            if ($id === null) $id = (string)$idx;

            $centroid = self::featureCentroid($feat);
            if ($centroid === null) continue;

            $items[] = ['id' => $id, 'lat' => $centroid['lat'], 'lng' => $centroid['lng']];
        }

        $n = count($items);
        if ($n === 0) return [];
        if ($n === 1) return [$items[0]['id']];

        // 2) farthest-first (maximin) sur les centroïdes
        $ffIdx = self::farthestFirstOrder($items, $metric, $seed); // indices 0..n-1

        // 3) permutation "pas doré" pour alimenter une palette triée par hue
        $perm = self::spreadIndices($n, $step);

        // 4) projeter : le k-ième choisi va à la position permutée perm[k]
        $out = array_fill(0, $n, null);
        foreach ($ffIdx as $rank => $iFeature) {
            $pos = $perm[$rank];
            $out[$pos] = $items[$iFeature]['id'];
        }

        // (sécurité) combler trous improbables
        for ($i = 0; $i < $n; $i++) if ($out[$i] === null) $out[$i] = $items[$i]['id'];

        return $out; // IDs dans l'ordre: palette[i] -> featureId $out[i]
    }

    // ---------- GeoJSON utils ----------

    private static function asFeatureCollection($geojson): array
    {
        if (is_array($geojson)) return $geojson;
        if (is_string($geojson)) {
            if (is_file($geojson)) {
                $s = file_get_contents($geojson);
                $j = json_decode($s, true);
                return is_array($j) ? $j : [];
            }
            // string JSON brut
            $j = json_decode($geojson, true);
            return is_array($j) ? $j : [];
        }
        return [];
    }

    private static function extractId(array $feature, array $idKeys): ?string
    {
        if (isset($feature['id'])) return (string)$feature['id'];
        $props = $feature['properties'] ?? null;
        if (is_array($props)) {
            foreach ($idKeys as $k) {
                if (array_key_exists($k, $props) && $props[$k] !== null && $props[$k] !== '') {
                    return (string)$props[$k];
                }
            }
        }
        return null;
    }

    private static function featureCentroid(array $feature): ?array
    {
        if (!isset($feature['geometry'])) return null;
        return self::geometryCentroid($feature['geometry']);
    }

    private static function geometryCentroid($geom): ?array
    {
        if (!$geom || !isset($geom['type'])) return null;
        $t = $geom['type'];
        $c = $geom['coordinates'] ?? null;

        switch ($t) {
            case 'Point':
                if (!is_array($c) || count($c) < 2) return null;
                return ['lat' => (float)$c[1], 'lng' => (float)$c[0]]; // GeoJSON: [lon,lat]

            case 'MultiPoint':
                return self::avgCoords($c);

            case 'LineString':
                return self::avgCoords($c);

            case 'MultiLineString':
                $acc = self::avgOfAverages($c);
                return $acc;

            case 'Polygon':
                return self::polygonCentroid($c);

            case 'MultiPolygon':
                $sumX = 0.0; $sumY = 0.0; $sumA = 0.0;
                if (!is_array($c)) return null;
                foreach ($c as $polygon) {
                    $cent = self::polygonCentroid($polygon);
                    if ($cent && isset($cent['_A']) && $cent['_A'] != 0.0) {
                        $sumX += $cent['_cx'] * $cent['_A'];
                        $sumY += $cent['_cy'] * $cent['_A'];
                        $sumA += $cent['_A'];
                    }
                }
                if (abs($sumA) < 1e-12) return null;
                return ['lat' => $sumY / $sumA, 'lng' => $sumX / $sumA];

            case 'GeometryCollection':
                $sumX = 0.0; $sumY = 0.0; $cnt = 0;
                foreach (($geom['geometries'] ?? []) as $g) {
                    $p = self::geometryCentroid($g);
                    if ($p) { $sumX += $p['lng']; $sumY += $p['lat']; $cnt++; }
                }
                if ($cnt === 0) return null;
                return ['lat' => $sumY / $cnt, 'lng' => $sumX / $cnt];

            default:
                return null;
        }
    }

    private static function avgCoords($coords): ?array
    {
        if (!is_array($coords) || empty($coords)) return null;
        $sx = 0.0; $sy = 0.0; $n = 0;
        foreach ($coords as $pt) {
            if (is_array($pt) && count($pt) >= 2) { $sx += $pt[0]; $sy += $pt[1]; $n++; }
        }
        if ($n === 0) return null;
        return ['lat' => $sy / $n, 'lng' => $sx / $n];
    }

    private static function avgOfAverages($multi): ?array
    {
        if (!is_array($multi) || empty($multi)) return null;
        $sx = 0.0; $sy = 0.0; $n = 0;
        foreach ($multi as $coords) {
            $p = self::avgCoords($coords);
            if ($p) { $sx += $p['lng']; $sy += $p['lat']; $n++; }
        }
        if ($n === 0) return null;
        return ['lat' => $sy / $n, 'lng' => $sx / $n];
    }

    /**
     * Centroid plan (approx) d'un Polygon (liste de rings). Utilise toutes les rings avec aire signée.
     * Retourne aussi _cx,_cy,_A (accumulateurs) pour pondérer les MultiPolygon.
     */
    private static function polygonCentroid($rings): ?array
    {
        if (!is_array($rings) || empty($rings)) return null;

        $Cx = 0.0; $Cy = 0.0; $A = 0.0;
        foreach ($rings as $ring) {
            if (!is_array($ring) || count($ring) < 4) continue; // au moins 4 points (fermé)
            $m = count($ring);
            $area = 0.0; $cx = 0.0; $cy = 0.0;
            for ($i = 0; $i < $m - 1; $i++) {
                $x0 = (float)$ring[$i][0];   $y0 = (float)$ring[$i][1];
                $x1 = (float)$ring[$i+1][0]; $y1 = (float)$ring[$i+1][1];
                $cross = $x0 * $y1 - $x1 * $y0;
                $area += $cross;
                $cx += ($x0 + $x1) * $cross;
                $cy += ($y0 + $y1) * $cross;
            }
            if (abs($area) < 1e-12) {
                // fallback: moyenne simple
                $p = self::avgCoords($ring);
                if ($p) { $Cx += $p['lng']; $Cy += $p['lat']; $A += 1.0; }
                continue;
            }
            $area *= 0.5;
            $cx /= (6.0 * $area);
            $cy /= (6.0 * $area);

            $Cx += $cx * $area;
            $Cy += $cy * $area;
            $A  += $area;
        }

        if (abs($A) < 1e-12) return null;
        $lng = $Cx / $A; $lat = $Cy / $A;
        return ['lat' => $lat, 'lng' => $lng, '_cx' => $lng, '_cy' => $lat, '_A' => $A];
    }

    // ---------- Farthest-first + permutation ----------

    private static function gcd(int $a, int $b): int {
        $a = abs($a); $b = abs($b);
        while ($b !== 0) { [$a, $b] = [$b, $a % $b]; }
        return $a;
    }

    private static function spreadIndices(int $n, ?int $step = null): array
    {
        if ($n <= 1) return [0];
        if ($step === null) {
            $step = max(1, (int)round($n / 1.618)); // ~phi
        } else {
            $step = (($step % $n) + $n) % $n;
            if ($step === 0) $step = 1;
        }
        while (self::gcd($step, $n) !== 1) {
            $step = ($step + 1) % $n;
            if ($step === 0) $step = 1;
        }
        $order = [];
        for ($i = 0, $cur = 0; $i < $n; $i++, $cur = ($cur + $step) % $n) {
            $order[] = $cur;
        }
        return $order;
    }

    private static function dist($a, $b, string $metric): float
    {
        if ($metric === 'euclid') {
            $dx = $a['lng'] - $b['lng'];
            $dy = $a['lat'] - $b['lat'];
            return sqrt($dx*$dx + $dy*$dy);
        }
        // haversine (km)
        $R = 6371.0088;
        $lat1 = deg2rad($a['lat']); $lat2 = deg2rad($b['lat']);
        $dlat = $lat2 - $lat1;
        $dlng = deg2rad($a['lng'] - $b['lng']);
        $sinDLat = sin($dlat / 2);
        $sinDLng = sin($dlng / 2);
        $h = $sinDLat*$sinDLat + cos($lat1) * cos($lat2) * $sinDLng*$sinDLng;
        return 2 * $R * asin(min(1.0, sqrt($h)));
    }

    private static function barycenter(array $items): array
    {
        $sx = 0.0; $sy = 0.0; $n = count($items);
        foreach ($items as $it) { $sx += $it['lat']; $sy += $it['lng']; }
        return ['lat' => $sx / $n, 'lng' => $sy / $n];
    }

    private static function farthestFirstOrder(array $items, string $metric, string $seedStrategy): array
    {
        $n = count($items);
        $selected = array_fill(0, $n, false);
        $minDist  = array_fill(0, $n, INF);
        $order    = [];

        // seed
        $seed = 0;
        if ($seedStrategy === 'pair') {
            $A = 0; $best = -1.0;
            for ($i = 1; $i < $n; $i++) {
                $d = self::dist($items[$i], $items[0], $metric);
                if ($d > $best) { $best = $d; $A = $i; }
            }
            $B = 0; $best = -1.0;
            for ($i = 0; $i < $n; $i++) {
                $d = self::dist($items[$i], $items[$A], $metric);
                if ($d > $best) { $best = $d; $B = $i; }
            }
            $seed = $A; // ou $B
        } else {
            $bc = self::barycenter($items);
            $seed = 0; $best = -1.0;
            for ($i = 0; $i < $n; $i++) {
                $d = self::dist($items[$i], $bc, $metric);
                if ($d > $best) { $best = $d; $seed = $i; }
            }
        }

        // init
        $selected[$seed] = true;
        $order[] = $seed;
        for ($i = 0; $i < $n; $i++) {
            if (!$selected[$i]) $minDist[$i] = self::dist($items[$i], $items[$seed], $metric);
        }

        // boucle
        for ($t = 1; $t < $n; $t++) {
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
}
