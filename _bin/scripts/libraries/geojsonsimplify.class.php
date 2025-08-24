<?php
declare(strict_types=1);

final class GeoJsonSimplify
{
    /**
     * Simplifie un fichier GeoJSON et écrit le résultat dans $output (ou in-place si $output est null).
     *
     * Options:
     * - tolerance (float)   : seuil RDP; si units='meters' (défaut) → mètres; sinon degrés. Défaut 5.0 (≈5 m).
     * - units ('meters'|'degrees') : unité de tolerance. Défaut 'meters'.
     * - precision (int)     : nombre de décimales lon/lat à garder (arrondi). Défaut 6.
     * - bbox ('none'|'feature'|'collection'|'both'|bool): recalcul des bbox. Défaut 'none'.
     * - dropDegenerate (bool): supprime anneaux/segments devenus dégénérés. Défaut true.
     */
    public static function simplifyFile(string $input, ?string $output = null, array $options = []): void
    {
        $output ??= $input;

        $json = @file_get_contents($input);
        if ($json === false) {
            throw new RuntimeException("Impossible de lire: $input");
        }
        $data = self::decodeJson($json);

        $data = self::simplify($data, $options);

        self::writeJsonFile($output, $data);
    }

    /**
     * Simplifie une structure GeoJSON (array ou string JSON) et renvoie un array.
     * @param array|string $geojson
     */
    public static function simplify(array|string $geojson, array $options = []): array
    {
        $data = is_string($geojson) ? self::decodeJson($geojson) : $geojson;

        $tolerance = (float)($options['tolerance'] ?? 5.0); // ~5 mètres
        $units     = strtolower((string)($options['units'] ?? 'meters'));
        $precision = (int)($options['precision'] ?? 6);
        $dropDeg   = (bool)($options['dropDegenerate'] ?? true);
        $bboxOpt   = $options['bbox'] ?? 'none';
        if ($bboxOpt === true)  $bboxOpt = 'both';
        if ($bboxOpt === false) $bboxOpt = 'none';
        $bboxOpt   = strtolower((string)$bboxOpt);

        // Retire d’anciennes bbox puisqu’on modifie la géométrie
        self::stripBBoxes($data);

        $data = self::simplifyAny($data, $tolerance, $units, $precision, $dropDeg);

        // Recalc bbox si demandé
        if ($bboxOpt === 'feature' || $bboxOpt === 'both') {
            if (($data['type'] ?? null) === 'FeatureCollection') {
                foreach ($data['features'] as $i => $f) {
                    $b = self::geometryBBox($f['geometry'] ?? null);
                    if ($b !== null) $data['features'][$i]['bbox'] = $b;
                }
            } elseif (($data['type'] ?? null) === 'Feature') {
                $b = self::geometryBBox($data['geometry'] ?? null);
                if ($b !== null) $data['bbox'] = $b;
            } else {
                $b = self::geometryBBox($data);
                if ($b !== null) $data['bbox'] = $b;
            }
        }
        if ($bboxOpt === 'collection' || $bboxOpt === 'both') {
            if (($data['type'] ?? null) === 'FeatureCollection') {
                $global = null;
                foreach ($data['features'] as $f) {
                    $b = $f['bbox'] ?? self::geometryBBox($f['geometry'] ?? null);
                    if ($b !== null) $global = self::bboxMerge($global, $b);
                }
                if ($global !== null) $data['bbox'] = $global;
            } else {
                // collection non applicable → ignoré
            }
        }

        return $data;
    }

    /** ------------------ Simplification cœur ------------------ */

    private static function simplifyAny(array $node, float $tol, string $units, int $prec, bool $dropDeg): array
    {
        $type = $node['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $features = [];
            foreach (($node['features'] ?? []) as $f) {
                if (!is_array($f) || ($f['type'] ?? null) !== 'Feature') continue;
                $g = $f['geometry'] ?? null;
                if ($g !== null) {
                    $f['geometry'] = self::simplifyGeometry($g, $tol, $units, $prec, $dropDeg);
                    if ($dropDeg && self::isGeometryEmpty($f['geometry'])) {
                        continue; // drop feature devenu vide
                    }
                }
                $features[] = $f;
            }
            $node['features'] = $features;
            return $node;
        }

        if ($type === 'Feature') {
            if (isset($node['geometry'])) {
                $node['geometry'] = self::simplifyGeometry($node['geometry'], $tol, $units, $prec, $dropDeg);
            }
            return $node;
        }

        // Géométrie
        return self::simplifyGeometry($node, $tol, $units, $prec, $dropDeg);
    }

    private static function simplifyGeometry(array $geom, float $tol, string $units, int $prec, bool $dropDeg): array
    {
        $type = $geom['type'] ?? null;

        switch ($type) {
            case 'Point':
                if (isset($geom['coordinates'][0], $geom['coordinates'][1])) {
                    $geom['coordinates'][0] = self::rnd($geom['coordinates'][0], $prec);
                    $geom['coordinates'][1] = self::rnd($geom['coordinates'][1], $prec);
                }
                return $geom;

            case 'MultiPoint':
                $coords = $geom['coordinates'] ?? [];
                foreach ($coords as $i => $p) {
                    if (isset($p[0], $p[1])) {
                        $coords[$i][0] = self::rnd($p[0], $prec);
                        $coords[$i][1] = self::rnd($p[1], $prec);
                    }
                }
                $geom['coordinates'] = $coords;
                return $geom;

            case 'LineString':
                $line = $geom['coordinates'] ?? [];
                $geom['coordinates'] = self::simplifyOpenLine($line, $tol, $units, $prec);
                if ($dropDeg && count($geom['coordinates']) < 2) {
                    $geom['coordinates'] = [];
                    $geom['type'] = 'LineString';
                }
                return $geom;

            case 'MultiLineString':
                $lines = $geom['coordinates'] ?? [];
                $out = [];
                foreach ($lines as $ln) {
                    $s = self::simplifyOpenLine($ln, $tol, $units, $prec);
                    if (!$dropDeg || count($s) >= 2) $out[] = $s;
                }
                $geom['coordinates'] = $out;
                return $geom;

            case 'Polygon':
                $rings = $geom['coordinates'] ?? [];
                $out = [];
                foreach ($rings as $r) {
                    $s = self::simplifyRing($r, $tol, $units, $prec);
                    if (!$dropDeg || self::isValidRing($s)) $out[] = $s;
                }
                $geom['coordinates'] = $out;
                return $geom;

            case 'MultiPolygon':
                $polys = $geom['coordinates'] ?? [];
                $outPolys = [];
                foreach ($polys as $poly) {
                    $outRings = [];
                    foreach ($poly as $r) {
                        $s = self::simplifyRing($r, $tol, $units, $prec);
                        if (!$dropDeg || self::isValidRing($s)) $outRings[] = $s;
                    }
                    if (!$dropDeg || count($outRings) > 0) $outPolys[] = $outRings;
                }
                $geom['coordinates'] = $outPolys;
                return $geom;

            case 'GeometryCollection':
                $geoms = $geom['geometries'] ?? [];
                foreach ($geoms as $i => $g) {
                    if (is_array($g)) {
                        $geoms[$i] = self::simplifyGeometry($g, $tol, $units, $prec, $dropDeg);
                    }
                }
                $geom['geometries'] = $geoms;
                return $geom;

            default:
                return $geom;
        }
    }

    /** --- RDP pour lignes ouvertes --- */
    private static function simplifyOpenLine(array $coords, float $tol, string $units, int $prec): array
    {
        $n = count($coords);
        if ($n <= 2) {
            return self::roundCoords($coords, $prec);
        }

        $lat0 = self::avgLat($coords);
        [$xs, $ys] = self::toXY($coords, $lat0, $units);

        $idx = self::rdpIndices($xs, $ys, $tol);
        if (empty($idx)) $idx = [0, $n - 1];

        $out = [];
        foreach ($idx as $i) {
            if (!isset($coords[$i][0], $coords[$i][1])) continue;
            $out[] = [ self::rnd($coords[$i][0], $prec), self::rnd($coords[$i][1], $prec) ];
        }
        // garantir au moins 2 points
        if (count($out) === 1 && $n >= 2) {
            $out[] = [ self::rnd($coords[$n-1][0], $prec), self::rnd($coords[$n-1][1], $prec) ];
        }
        return $out;
    }

    /** --- RDP pour anneaux fermés (polygones) --- */
    private static function simplifyRing(array $ring, float $tol, string $units, int $prec): array
    {
        $m = count($ring);
        if ($m <= 4) {
            // juste arrondir + forcer fermeture
            $ring = self::roundCoords($ring, $prec);
            return self::ensureClosed($ring);
        }

        // enlever le dernier point s'il duplique le premier
        $closed = self::isClosed($ring);
        if ($closed) array_pop($ring);
        $n = count($ring);

        $lat0 = self::avgLat($ring);
        [$xs, $ys] = self::toXY($ring, $lat0, $units);

        $idx = self::rdpIndices($xs, $ys, $tol);
        if (count($idx) < 3) {
            // minimum pour un anneau ouvert
            $idx = [0, (int)floor($n/2), $n-1];
        }

        $out = [];
        foreach ($idx as $i) {
            if (!isset($ring[$i][0], $ring[$i][1])) continue;
            $out[] = [ self::rnd($ring[$i][0], $prec), self::rnd($ring[$i][1], $prec) ];
        }

        $out = self::ensureClosed($out);
        return $out;
    }

    /** Indices conservés par RDP (itératif) sur une polyline */
    private static function rdpIndices(array $xs, array $ys, float $epsilon): array
    {
        $n = count($xs);
        if ($n <= 2) return [0, max(0, $n-1)];

        $stack = [[0, $n-1]];
        $keep  = array_fill(0, $n, false);
        $keep[0] = $keep[$n-1] = true;

        while (!empty($stack)) {
            [$a, $b] = array_pop($stack);
            $ax = $xs[$a]; $ay = $ys[$a];
            $bx = $xs[$b]; $by = $ys[$b];
            $dx = $bx - $ax; $dy = $by - $ay;
            $len2 = $dx*$dx + $dy*$dy;

            $maxD = 0.0; $idx = -1;
            for ($i = $a+1; $i < $b; $i++) {
                $px = $xs[$i]; $py = $ys[$i];
                if ($len2 == 0.0) {
                    $dist = hypot($px - $ax, $py - $ay);
                } else {
                    $t = ( ($px-$ax)*$dx + ($py-$ay)*$dy ) / $len2;
                    $t = max(0.0, min(1.0, $t));
                    $qx = $ax + $t*$dx; $qy = $ay + $t*$dy;
                    $dist = hypot($px - $qx, $py - $qy);
                }
                if ($dist > $maxD) { $maxD = $dist; $idx = $i; }
            }

            if ($maxD > $epsilon && $idx !== -1) {
                $keep[$idx] = true;
                $stack[] = [$a, $idx];
                $stack[] = [$idx, $b];
            }
        }

        $out = [];
        for ($i=0; $i<$n; $i++) if ($keep[$i]) $out[] = $i;
        return $out;
    }

    /** ------------------ Utilitaires géo & bbox ------------------ */

    private static function toXY(array $coords, float $lat0, string $units): array
    {
        // Retourne deux tableaux [xs],[ys] dans l’unité choisie.
        $xs = []; $ys = [];
        if ($units === 'degrees') {
            foreach ($coords as $p) { $xs[] = (float)$p[0]; $ys[] = (float)$p[1]; }
            return [$xs, $ys];
        }
        // meters (équirectangulaire locale)
        $R = 6371008.8; // rayon moyen en m
        $cos0 = cos(deg2rad($lat0));
        foreach ($coords as $p) {
            $lon = (float)$p[0]; $lat = (float)$p[1];
            $x = $R * deg2rad($lon) * $cos0;
            $y = $R * deg2rad($lat);
            $xs[] = $x; $ys[] = $y;
        }
        return [$xs, $ys];
    }

    private static function avgLat(array $coords): float
    {
        $sum = 0.0; $n = 0;
        foreach ($coords as $p) {
            if (isset($p[1]) && is_numeric($p[1])) { $sum += (float)$p[1]; $n++; }
        }
        return $n ? $sum / $n : 0.0;
    }

    private static function isClosed(array $ring): bool
    {
        $n = count($ring);
        if ($n < 2) return false;
        return isset($ring[0][0], $ring[0][1], $ring[$n-1][0], $ring[$n-1][1]) &&
               $ring[0][0] == $ring[$n-1][0] && $ring[0][1] == $ring[$n-1][1];
    }

    private static function ensureClosed(array $ring): array
    {
        $n = count($ring);
        if ($n == 0) return $ring;
        if (!self::isClosed($ring)) $ring[] = $ring[0];
        return $ring;
    }

    private static function isValidRing(array $ring): bool
    {
        // Un anneau valide doit avoir au moins 4 points (premier = dernier).
        return count($ring) >= 4 && self::isClosed($ring);
    }

    private static function roundCoords(array $coords, int $prec): array
    {
        foreach ($coords as $i => $p) {
            if (isset($p[0], $p[1])) {
                $coords[$i][0] = self::rnd($p[0], $prec);
                $coords[$i][1] = self::rnd($p[1], $prec);
            }
        }
        return $coords;
    }

    private static function rnd(float $v, int $prec): float
    {
        return (float) number_format($v, $prec, '.', '');
    }

    private static function isGeometryEmpty(?array $g): bool
    {
        if (!$g || !isset($g['type'])) return true;
        $t = $g['type'];
        $c = $g['coordinates'] ?? null;
        return in_array($t, ['LineString','MultiLineString','Polygon','MultiPolygon'], true)
            && (empty($c) || $c === [[]]);
    }

    /** Retire toutes les bbox existantes dans l’arbre */
    private static function stripBBoxes(array &$node): void
    {
        if (is_array($node) && array_key_exists('bbox', $node)) unset($node['bbox']);
        foreach ($node as &$v) if (is_array($v)) self::stripBBoxes($v);
    }

    /** ---- BBox helpers (2D) ---- */
    private static function geometryBBox(?array $geom): ?array
    {
        if (!$geom || !isset($geom['type'])) return null;
        $type = $geom['type'];

        switch ($type) {
            case 'Point': {
                $c = $geom['coordinates'] ?? null;
                if (!is_array($c) || count($c) < 2) return null;
                $x = (float)$c[0]; $y = (float)$c[1];
                return [$x,$y,$x,$y];
            }
            case 'MultiPoint':
            case 'LineString':
            case 'MultiLineString':
            case 'Polygon':
            case 'MultiPolygon': {
                $bbox = null;
                self::bboxFromCoords($geom['coordinates'] ?? [], $bbox);
                return $bbox;
            }
            case 'GeometryCollection': {
                $bbox = null;
                foreach (($geom['geometries'] ?? []) as $g) {
                    $b = self::geometryBBox($g);
                    if ($b !== null) $bbox = self::bboxMerge($bbox, $b);
                }
                return $bbox;
            }
            default:
                return null;
        }
    }

    private static function bboxFromCoords($coords, ?array &$bbox): void
    {
        if (is_array($coords) &&
            isset($coords[0], $coords[1]) &&
            !is_array($coords[0]) && !is_array($coords[1]) &&
            is_numeric($coords[0]) && is_numeric($coords[1])) {

            $x = (float)$coords[0]; $y = (float)$coords[1];
            if ($bbox === null) $bbox = [$x,$y,$x,$y];
            else {
                if ($x < $bbox[0]) $bbox[0] = $x;
                if ($y < $bbox[1]) $bbox[1] = $y;
                if ($x > $bbox[2]) $bbox[2] = $x;
                if ($y > $bbox[3]) $bbox[3] = $y;
            }
            return;
        }
        if (is_array($coords)) {
            foreach ($coords as $c) self::bboxFromCoords($c, $bbox);
        }
    }

    private static function bboxMerge(?array $a, ?array $b): ?array
    {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return [
            min($a[0], $b[0]),
            min($a[1], $b[1]),
            max($a[2], $b[2]),
            max($a[3], $b[3]),
        ];
    }

    /** --------------- IO & JSON --------------- */

    private static function decodeJson(string $json): array
    {
        try {
            /** @var array $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('JSON invalide: '.$e->getMessage(), previous: $e);
        }
    }

    private static function writeJsonFile(string $path, array $data): void
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new RuntimeException('Échec d’encodage JSON');
        }
        if (@file_put_contents($path, $json) === false) {
            throw new RuntimeException("Impossible d'écrire: $path");
        }
    }
}
