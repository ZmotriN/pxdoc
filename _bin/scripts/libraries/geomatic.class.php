<?php

class Geomatic
{


    public static function featureBounds($feature) /* : object */
    {
        $get = function ($v, string $k) {
            if (is_array($v))  return $v[$k]  ?? null;
            if (is_object($v)) return $v->$k ?? null;
            return null;
        };

        // 1) Utiliser bbox si présent: [minLng, minLat, maxLng, maxLat]
        $bbox = $get($feature, 'bbox');
        if (is_array($bbox) && count($bbox) >= 4) {
            [$minx, $miny, $maxx, $maxy] = array_map('floatval', array_slice($bbox, 0, 4));
            return (object)[
                'top'    => $maxy,
                'bottom' => $miny,
                'left'   => $minx,
                'right'  => $maxx,
                'center' => (object)['lat' => ($miny + $maxy) / 2, 'lng' => ($minx + $maxx) / 2],
            ];
        }

        // 2) Sinon, parcourir la géométrie
        $geom = $get($feature, 'geometry');
        if (!$geom) return (object)['top' => null, 'bottom' => null, 'left' => null, 'right' => null, 'center' => null];

        $minx = INF;
        $miny = INF;
        $maxx = -INF;
        $maxy = -INF;

        $accum = function ($node) use (&$minx, &$miny, &$maxx, &$maxy, &$accum) {
            if (is_array($node)) {
                // Paire [lng, lat] ?
                if (isset($node[0], $node[1]) && is_numeric($node[0]) && is_numeric($node[1])) {
                    $x = (float)$node[0];
                    $y = (float)$node[1];
                    if ($x < $minx) $minx = $x;
                    if ($x > $maxx) $maxx = $x;
                    if ($y < $miny) $miny = $y;
                    if ($y > $maxy) $maxy = $y;
                    return;
                }
                foreach ($node as $child) $accum($child);
            } elseif (is_object($node)) {
                // Sécurité: si jamais on reçoit un objet imbriqué
                foreach (get_object_vars($node) as $child) $accum($child);
            }
        };

        $type = strtoupper((string)$get($geom, 'type'));
        if ($type === 'GEOMETRYCOLLECTION') {
            foreach ($get($geom, 'geometries') ?? [] as $g) {
                $accum($get($g, 'coordinates'));
            }
        } else {
            $accum($get($geom, 'coordinates'));
        }

        if ($minx === INF) return (object)['top' => null, 'bottom' => null, 'left' => null, 'right' => null, 'center' => null];

        return (object)[
            'top'    => $maxy,
            'bottom' => $miny,
            'left'   => $minx,
            'right'  => $maxx,
            'center' => (object)['lat' => ($miny + $maxy) / 2, 'lng' => ($minx + $maxx) / 2],
        ];
    }





    public static function pointInFeature(float $lat, float $lng, array|object $feature): bool
    {
        $geom = self::get($feature, 'geometry');
        if (!$geom) return false;

        $type   = strtoupper((string) self::get($geom, 'type'));
        $coords = self::get($geom, 'coordinates') ?? [];

        $x = $lng; // GeoJSON = [lng, lat]
        $y = $lat;

        if ($type === 'POLYGON') {
            return self::pointInPolygonCoords($x, $y, $coords);
        } elseif ($type === 'MULTIPOLYGON') {
            foreach ($coords as $poly) {
                if (self::pointInPolygonCoords($x, $y, $poly)) return true;
            }
            return false;
        }
        return false;
    }


    public static function pointInPolygonCoords(float $x, float $y, array $rings): bool
    {
        if (empty($rings) || empty($rings[0])) return false;

        // Doit être dans l’anneau extérieur…
        if (!self::pointInRing($x, $y, $rings[0])) return false;
        // …et hors de tous les anneaux intérieurs (trous)
        $n = count($rings);
        for ($i = 1; $i < $n; $i++) {
            if (!empty($rings[$i]) && self::pointInRing($x, $y, $rings[$i])) return false;
        }
        return true;
    }


    public static function pointInRing(float $x, float $y, array $ring): bool
    {
        $n = count($ring);
        if ($n < 3) return false;

        // BBox rapide
        $minx = $miny = PHP_FLOAT_MAX;
        $maxx = $maxy = -PHP_FLOAT_MAX;
        for ($i = 0; $i < $n; $i++) {
            $xi = (float)$ring[$i][0]; // lng
            $yi = (float)$ring[$i][1]; // lat
            if ($xi < $minx) $minx = $xi;
            if ($xi > $maxx) $maxx = $xi;
            if ($yi < $miny) $miny = $yi;
            if ($yi > $maxy) $maxy = $yi;
        }
        if ($x < $minx || $x > $maxx || $y < $miny || $y > $maxy) return false;

        // Ray casting + inclusion du bord
        $inside = false;
        $j = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $xi = (float)$ring[$i][0];
            $yi = (float)$ring[$i][1];
            $xj = (float)$ring[$j][0];
            $yj = (float)$ring[$j][1];

            if (self::pointOnSegment($x, $y, $xi, $yi, $xj, $yj)) return true; // bord = inside

            $intersect = (($yi > $y) !== ($yj > $y))
                      && ($x < ($xj - $xi) * (($y - $yi) / ($yj - $yi)) + $xi);
            if ($intersect) $inside = !$inside;

            $j = $i;
        }
        return $inside;
    }


    public static function pointOnSegment(float $x, float $y, float $x1, float $y1, float $x2, float $y2): bool
    {
        // Colinéarité et appartenance au segment (sans sqrt)
        $cross = ($y - $y1) * ($x2 - $x1) - ($x - $x1) * ($y2 - $y1);
        if (abs($cross) > 1e-12) return false;

        $dot = ($x - $x1) * ($x2 - $x1) + ($y - $y1) * ($y2 - $y1);
        if ($dot < 0) return false;

        $sqLen = ($x2 - $x1) * ($x2 - $x1) + ($y2 - $y1) * ($y2 - $y1);
        if ($dot > $sqLen) return false;

        return true;
    }


    private static function get($v, string $k) {
        if (is_array($v))  return $v[$k]  ?? null;
        if (is_object($v)) return $v->$k ?? null;
        return null;
    }

}
