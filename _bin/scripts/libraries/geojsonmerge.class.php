<?php
declare(strict_types=1);

final class GeoJsonMerge
{
    /**
     * Merge plusieurs fichiers/motifs en un seul FeatureCollection et l’écrit dans $output.
     *
     * @param array<string> $inputs  Liste de chemins de fichiers GeoJSON ou motifs glob (*.geojson, etc.)
     * @param string $output         Fichier de sortie
     * @param array{
     *   bbox?: bool|string,         // false|'none' (défaut), 'feature', 'collection', 'both'|true
     *   tagSource?: bool,           // ajoute une propriété source avec le nom de fichier (défaut: false)
     *   sourceKey?: string,         // clé de la propriété source (défaut: "_src")
     *   assignIds?: bool,           // assigne un id incrémental si absent (défaut: false)
     *   skipInvalid?: bool          // ignore silencieusement les entrées invalides (défaut: true)
     * } $options
     */
    public static function mergeFiles(array $inputs, string $output, array $options = []): void
    {
        $paths = self::expandInputs($inputs);
        $features = [];
        $assignIds = (bool)($options['assignIds'] ?? false);
        $tagSource = (bool)($options['tagSource'] ?? false);
        $sourceKey = (string)($options['sourceKey'] ?? '_src');
        $skipInvalid = (bool)($options['skipInvalid'] ?? true);

        $idCounter = 1;

        foreach ($paths as $path) {
            $json = @file_get_contents($path);
            if ($json === false) {
                if ($skipInvalid) continue;
                throw new RuntimeException("Impossible de lire: $path");
            }

            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                if ($skipInvalid) continue;
                throw new InvalidArgumentException("JSON invalide ($path): " . $e->getMessage(), previous: $e);
            }

            foreach (self::toFeatures($data, $skipInvalid) as $feat) {
                // Nettoie une éventuelle bbox obsolète (sera recalculée si demandé)
                if (isset($feat['bbox'])) unset($feat['bbox']);

                if ($tagSource) {
                    $feat['properties'] ??= [];
                    $feat['properties'][$sourceKey] = basename($path);
                }
                if ($assignIds && !isset($feat['id'])) {
                    $feat['id'] = $idCounter++;
                }
                $features[] = $feat;
            }
        }

        $fc = ['type' => 'FeatureCollection', 'features' => $features];

        // BBoxes (optionnelles)
        $bboxOpt = $options['bbox'] ?? 'none';
        if ($bboxOpt === true) $bboxOpt = 'both';
        if ($bboxOpt === false) $bboxOpt = 'none';
        $bboxOpt = strtolower((string)$bboxOpt);

        if ($bboxOpt === 'feature' || $bboxOpt === 'both') {
            foreach ($fc['features'] as $i => $f) {
                $b = self::geometryBBox($f['geometry'] ?? null);
                if ($b !== null) $fc['features'][$i]['bbox'] = $b;
            }
        }
        if ($bboxOpt === 'collection' || $bboxOpt === 'both') {
            $global = null;
            foreach ($fc['features'] as $f) {
                $b = $f['bbox'] ?? self::geometryBBox($f['geometry'] ?? null);
                if ($b !== null) $global = self::bboxMerge($global, $b);
            }
            if ($global !== null) $fc['bbox'] = $global;
        }

        self::writeJsonFile($output, $fc);
    }

    /** ---------- Helpers lecture/écriture ---------- */

    /** @param array<string> $inputs */
    private static function expandInputs(array $inputs): array
    {
        $out = [];
        foreach ($inputs as $p) {
            if (self::looksLikeGlob($p)) {
                $matches = glob($p, GLOB_BRACE) ?: [];
                // Tri stable pour reproductibilité
                sort($matches, SORT_STRING);
                $out = array_merge($out, $matches);
            } else {
                $out[] = $p;
            }
        }
        // Uniques en conservant l’ordre
        return array_values(array_unique($out));
    }

    private static function looksLikeGlob(string $path): bool
    {
        return strpbrk($path, "*?[]{}") !== false;
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

    /** ---------- Conversion en features ---------- */

    /**
     * @return array<int, array{type:'Feature',properties?:array,geometry:?array}>
     */
    private static function toFeatures(array $data, bool $skipInvalid): array
    {
        $type = $data['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $features = $data['features'] ?? [];
            // Filtre minimal pour s'assurer du type Feature
            $out = [];
            foreach ($features as $f) {
                if (is_array($f) && ($f['type'] ?? null) === 'Feature') {
                    $out[] = $f;
                } elseif (!$skipInvalid) {
                    throw new InvalidArgumentException('Entrée FeatureCollection contient un élément non-Feature');
                }
            }
            return $out;
        }

        if ($type === 'Feature') {
            return [$data];
        }

        // Géométrie nue → on l’enveloppe en Feature
        if (is_array($data) && isset($data['type'], $data['coordinates']) || $type === 'GeometryCollection') {
            return [[
                'type' => 'Feature',
                'properties' => [],
                'geometry' => $data,
            ]];
        }

        if ($skipInvalid) return [];
        throw new InvalidArgumentException('Entrée non reconnue (ni FeatureCollection, ni Feature, ni Géométrie)');
    }

    /** ---------- BBox (2D) ---------- */

    /** @return ?array{0:float,1:float,2:float,3:float} [minLon,minLat,maxLon,maxLat] */
    private static function geometryBBox(?array $geom): ?array
    {
        if (!$geom || !isset($geom['type'])) return null;
        $type = $geom['type'];

        switch ($type) {
            case 'Point': {
                $c = $geom['coordinates'] ?? null;
                if (!is_array($c) || count($c) < 2) return null;
                $x = (float)$c[0]; $y = (float)$c[1];
                return [$x, $y, $x, $y];
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

    /** @param mixed $coords */
    private static function bboxFromCoords(mixed $coords, ?array &$bbox): void
    {
        if (is_array($coords) &&
            isset($coords[0], $coords[1]) &&
            !is_array($coords[0]) && !is_array($coords[1]) &&
            is_numeric($coords[0]) && is_numeric($coords[1])) {

            $x = (float)$coords[0];
            $y = (float)$coords[1];

            if ($bbox === null) {
                $bbox = [$x, $y, $x, $y];
            } else {
                if ($x < $bbox[0]) $bbox[0] = $x;
                if ($y < $bbox[1]) $bbox[1] = $y;
                if ($x > $bbox[2]) $bbox[2] = $x;
                if ($y > $bbox[3]) $bbox[3] = $y;
            }
            return;
        }

        if (is_array($coords)) {
            foreach ($coords as $c) {
                self::bboxFromCoords($c, $bbox);
            }
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
}
