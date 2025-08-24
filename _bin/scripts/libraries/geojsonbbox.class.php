<?php
declare(strict_types=1);

final class GeoJsonBBox
{
    /** Ajoute des bbox à un fichier GeoJSON. Écrit dans $output (ou remplace $input si $output est null). */
    public static function addBBoxesToFile(string $input, ?string $output = null): void
    {
        $output ??= $input;

        $json = @file_get_contents($input);
        if ($json === false) {
            throw new RuntimeException("Impossible de lire le fichier: $input");
        }

        $data = self::decodeJson($json);
        $data = self::addBBoxes($data);

        self::writeJsonFile($output, $data);
    }

    /**
     * Ajoute des bbox à une structure GeoJSON.
     * @param array|string $geojson  tableau PHP (déjà décodé) ou string JSON
     * @return array  structure GeoJSON modifiée (tableau)
     */
    public static function addBBoxes(array|string $geojson): array
    {
        $data = is_string($geojson) ? self::decodeJson($geojson) : $geojson;

        $type = $data['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $collectionBbox = null;

            foreach ($data['features'] as $i => $feature) {
                $bbox = self::geometryBBox($feature['geometry'] ?? null);
                if ($bbox !== null) {
                    $data['features'][$i]['bbox'] = $bbox;
                    $collectionBbox = self::bboxMerge($collectionBbox, $bbox);
                } else {
                    unset($data['features'][$i]['bbox']);
                }
            }

            if ($collectionBbox !== null) {
                $data['bbox'] = $collectionBbox;
            } else {
                unset($data['bbox']);
            }

        } elseif ($type === 'Feature') {
            $bbox = self::geometryBBox($data['geometry'] ?? null);
            if ($bbox !== null) {
                $data['bbox'] = $bbox;
            }

        } else {
            // Géométrie "nue" (ou cas atypique) : on essaie quand même
            $bbox = self::geometryBBox($data);
            if ($bbox !== null) {
                $data['bbox'] = $bbox;
            }
        }

        return $data;
    }

    /** Décode un JSON en lançant une exception explicite en cas d’erreur. */
    public static function decodeJson(string $json): array
    {
        try {
            /** @var array $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('JSON invalide: ' . $e->getMessage(), previous: $e);
        }
    }

    /** Écrit un fichier JSON joliment formaté. */
    public static function writeJsonFile(string $path, array $data): void
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new RuntimeException('Échec d’encodage JSON: ' . self::jsonLastErrorMsg());
        }
        if (@file_put_contents($path, $json) === false) {
            throw new RuntimeException("Impossible d'écrire le fichier: $path");
        }
    }

    /** Retourne le message d’erreur JSON natif (utile si on n’utilise pas JSON_THROW_ON_ERROR). */
    public static function jsonLastErrorMsg(): string
    {
        return function_exists('json_last_error_msg') ? json_last_error_msg() : 'Erreur JSON inconnue';
    }

    /** --------- Privé : cœur du calcul des bbox --------- */

    /** Calcule la bbox d’une géométrie GeoJSON (ou null si impossible). */
    private static function geometryBBox(?array $geom): ?array
    {
        if (!$geom || !isset($geom['type'])) return null;
        $type = $geom['type'];

        switch ($type) {
            case 'Point': {
                $c = $geom['coordinates'] ?? null;
                if (!is_array($c) || count($c) < 2) return null;
                $x = (float)$c[0]; $y = (float)$c[1];
                return [$x, $y, $x, $y]; // [minLon, minLat, maxLon, maxLat]
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
                    if ($b !== null) {
                        $bbox = self::bboxMerge($bbox, $b);
                    }
                }
                return $bbox;
            }

            default:
                return null;
        }
    }

    /**
     * Parcourt récursivement un arbre de coordonnées GeoJSON et étend $bbox (2D).
     * Feuille attendue: [x, y, (z...)].
     * @param mixed $coords
     * @param ?array $bbox
     */
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

    /** Fusionne deux bbox (ou renvoie l’une si l’autre est null). */
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
