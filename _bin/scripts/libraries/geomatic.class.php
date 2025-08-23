<?php

class Geomatic
{
    /**
     * Teste si un point est dans un Feature/Geometry GeoJSON.
     * $point : [lng,lat] OU objet {lng,lat} OU {x,y}
     * $opts  : [
     *   'epsilon' => 1e-9,        // tolérance pour "sur le bord"
     *   'order'   => 'lnglat',    // 'lnglat' ou 'latlng' si tes coords sont [lat,lng]
     * ]
     */
    public static function pointInFeature($feature, $point, array $opts = []): bool
    {
        $eps   = $opts['epsilon'] ?? 1e-9;
        $order = $opts['order'] ?? 'lnglat';
        [$px, $py] = self::asLngLat($point, $order);

        // 1) BBox rapide si possible
        $b = self::featureBounds($feature);
        if ($b && !self::pointInBounds($px, $py, $b)) {
            return false;
        }

        // 2) Dispatch par type
        $type = self::getType($feature);
        switch ($type) {
            case 'Feature':
                return self::pointInFeature(self::get($feature, 'geometry'), $point, $opts);

            case 'FeatureCollection':
                $features = self::get($feature, 'features') ?? [];
                foreach ($features as $f) {
                    if (self::pointInFeature($f, $point, $opts)) return true;
                }
                return false;

            case 'GeometryCollection':
                $geoms = self::get($feature, 'geometries') ?? [];
                foreach ($geoms as $g) {
                    if (self::pointInFeature($g, $point, $opts)) return true;
                }
                return false;

            case 'Polygon':
                $coords = self::get($feature, 'coordinates') ?? [];
                return self::pointInPolygon($px, $py, $coords, $eps);

            case 'MultiPolygon':
                $polys = self::get($feature, 'coordinates') ?? [];
                foreach ($polys as $poly) {
                    if (self::pointInPolygon($px, $py, $poly, $eps)) return true;
                }
                return false;

            case 'Point':
                // “Inside” si exactement le même point (rarement utile)
                $c = self::get($feature, 'coordinates') ?? [];
                if (count($c) >= 2) {
                    [$x,$y] = $c;
                    return (abs($x - $px) <= $eps && abs($y - $py) <= $eps);
                }
                return false;

            default:
                // LineString, MultiLineString… -> considérer “dehors”.
                return false;
        }
    }

    /** Test bbox simple */
    private static function pointInBounds(float $x, float $y, array $b): bool
    {
        // $b = [minX, minY, maxX, maxY]
        return $x >= $b[0] && $x <= $b[2] && $y >= $b[1] && $y <= $b[3];
    }

    /** Polygon = [outerRing, hole1, hole2, ...]; chaque ring = [[lng,lat], ...] */
    private static function pointInPolygon(float $px, float $py, array $rings, float $eps): bool
    {
        if (empty($rings)) return false;

        // 1) L’anneau extérieur doit contenir le point
        if (!self::pointInRingInclusive($px, $py, $rings[0], $eps)) {
            return false;
        }

        // 2) Si dans un trou -> dehors
        $n = count($rings);
        for ($i = 1; $i < $n; $i++) {
            if (self::pointInRingInclusive($px, $py, $rings[$i], $eps)) {
                return false;
            }
        }
        return true;
    }

    /** Ray casting + inclusion des bords (segments/vertices) */
    private static function pointInRingInclusive(float $px, float $py, array $ring, float $eps): bool
    {
        $cnt = count($ring);
        if ($cnt < 3) return false;

        // S’assurer que le ring est fermé (facultatif pour l’algorithme,
        // mais utile pour les vérifs de segments “on-edge”)
        $x0 = $ring[0][0]; $y0 = $ring[0][1];
        $xn = $ring[$cnt-1][0]; $yn = $ring[$cnt-1][1];
        if ($x0 !== $xn || $y0 !== $yn) {
            $ring[] = [$x0, $y0];
            $cnt++;
        }

        // 1) Si sur un bord: “inside”
        for ($i = 0; $i < $cnt - 1; $i++) {
            [$x1,$y1] = $ring[$i];
            [$x2,$y2] = $ring[$i+1];
            if (self::pointOnSegment($px, $py, $x1, $y1, $x2, $y2, $eps)) {
                return true;
            }
        }

        // 2) Ray casting (parité) – robuste aux sommets et horizontaux
        $inside = false;
        for ($i = 0, $j = $cnt - 1; $i < $cnt; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            // Ignore les segments horizontaux pour l'intersection stricte,
            // ils sont couverts par le test “onSegment” au-dessus.
            if (abs($yi - $yj) < $eps) continue;

            // Est-ce que le rayon croise le segment ?
            // Utilise une petite marge sur py pour éviter les bascules au niveau des sommets.
            $minY = min($yi, $yj);
            $maxY = max($yi, $yj);

            $strictBelowMax = ($py < $maxY - $eps);
            $atOrAboveMin    = ($py >= $minY);

            if ($atOrAboveMin && $strictBelowMax) {
                // abscisse de l’intersection du segment avec la horizontale y=py
                $xint = $xi + ($py - $yi) * ($xj - $xi) / ($yj - $yi);
                if ($xint > $px + $eps) {
                    $inside = !$inside;
                }
            }
        }

        return $inside;
    }

    /** Test “sur le segment” avec tolérance (distance point-segment <= eps) */
    private static function pointOnSegment(float $px, float $py, float $x1, float $y1, float $x2, float $y2, float $eps): bool
    {
        // Projection paramétrique t de P sur [A,B]
        $dx = $x2 - $x1; $dy = $y2 - $y1;
        $len2 = $dx*$dx + $dy*$dy;
        if ($len2 === 0.0) {
            // A et B confondus : tester distance à A
            $d2 = ($px - $x1)*($px - $x1) + ($py - $y1)*($py - $y1);
            return $d2 <= $eps*$eps;
        }
        $t = (($px - $x1)*$dx + ($py - $y1)*$dy) / $len2;

        if ($t < 0.0) { $qx = $x1; $qy = $y1; }
        elseif ($t > 1.0) { $qx = $x2; $qy = $y2; }
        else { $qx = $x1 + $t*$dx; $qy = $y1 + $t*$dy; }

        $d2 = ($px - $qx)*($px - $qx) + ($py - $qy)*($py - $qy);
        return $d2 <= $eps*$eps;
    }

    /** Renvoie le type GeoJSON cohérent, que $feature soit array/objet */
    private static function getType($feature): ?string
    {
        $t = self::get($feature, 'type');
        if (is_string($t)) return $t;
        if (is_array($feature) && isset($feature['geometry']['type'])) return $feature['geometry']['type'];
        if (is_object($feature) && isset($feature->geometry->type))    return $feature->geometry->type;
        return null;
    }

    /** Normalise un point en [lng,lat] */
    private static function asLngLat($v, string $order = 'lnglat'): array
    {
        if (is_array($v)) {
            // [lng,lat] (par défaut) ou [lat,lng] si $order == 'latlng'
            if (isset($v[0], $v[1])) {
                return ($order === 'latlng') ? [ (float)$v[1], (float)$v[0] ]
                                             : [ (float)$v[0], (float)$v[1] ];
            }
            // {lng,lat} ou {x,y}
            if (isset($v['lng'], $v['lat'])) return [(float)$v['lng'], (float)$v['lat']];
            if (isset($v['x'], $v['y']))     return [(float)$v['x'],   (float)$v['y']];
        } elseif (is_object($v)) {
            if (isset($v->lng, $v->lat)) return [(float)$v->lng, (float)$v->lat];
            if (isset($v->x,   $v->y))   return [(float)$v->x,   (float)$v->y];
        }
        throw new \InvalidArgumentException('Point invalide');
    }

    /**
     * Calcule (ou lit) la bbox d’un Feature/Geometry GeoJSON.
     * Retourne [minX, minY, maxX, maxY] ou null si impossible.
     */
    public static function featureBounds($feature): ?array
    {
        $get = fn($v,$k)=> self::get($v,$k);

        // Utiliser bbox si présent: [minLng, minLat, maxLng, maxLat]
        $bbox = $get($feature, 'bbox');
        if (is_array($bbox) && count($bbox) >= 4) {
            return [ (float)$bbox[0], (float)$bbox[1], (float)$bbox[2], (float)$bbox[3] ];
        }

        // Sinon balayer les coordonnées
        $coords = null;
        $type = self::getType($feature);
        if ($type === null) return null;

        switch ($type) {
            case 'Feature':
                return self::featureBounds($get($feature, 'geometry'));

            case 'FeatureCollection':
                $ok = false;
                $minx=$miny=INF; $maxx=$maxy=-INF;
                foreach ($get($feature,'features') ?? [] as $f) {
                    $b = self::featureBounds($f);
                    if (!$b) continue;
                    $ok = true;
                    $minx = min($minx, $b[0]); $miny = min($miny, $b[1]);
                    $maxx = max($maxx, $b[2]); $maxy = max($maxy, $b[3]);
                }
                return $ok ? [$minx,$miny,$maxx,$maxy] : null;

            case 'GeometryCollection':
                $ok = false;
                $minx=$miny=INF; $maxx=$maxy=-INF;
                foreach ($get($feature,'geometries') ?? [] as $g) {
                    $b = self::featureBounds($g);
                    if (!$b) continue;
                    $ok = true;
                    $minx = min($minx, $b[0]); $miny = min($miny, $b[1]);
                    $maxx = max($maxx, $b[2]); $maxy = max($maxy, $b[3]);
                }
                return $ok ? [$minx,$miny,$maxx,$maxy] : null;

            case 'Polygon':
            case 'MultiPolygon':
            case 'LineString':
            case 'MultiLineString':
            case 'Point':
            case 'MultiPoint':
                $coords = $get($feature, 'coordinates');
                break;
        }

        if ($coords === null) return null;

        $minx=$miny=INF; $maxx=$maxy=-INF;
        $stack = [$coords];
        while ($stack) {
            $node = array_pop($stack);
            if (is_array($node)) {
                if (count($node) >= 2 && is_numeric($node[0]) && is_numeric($node[1])) {
                    $x = (float)$node[0]; $y = (float)$node[1];
                    if ($x < $minx) $minx = $x;
                    if ($x > $maxx) $maxx = $x;
                    if ($y < $miny) $miny = $y;
                    if ($y > $maxy) $maxy = $y;
                } else {
                    foreach ($node as $child) $stack[] = $child;
                }
            }
        }
        if ($minx === INF) return null;
        return [$minx,$miny,$maxx,$maxy];
    }

    /** Helpers sûrs pour array/objet */
    private static function get($v, string $k) {
        if (is_array($v))  return $v[$k]  ?? null;
        if (is_object($v)) return $v->$k ?? null;
        return null;
    }
}
