<?php
/**
 * Base class for all GIS data type classes
 */

declare(strict_types=1);

namespace PhpMyAdmin\Gis;

use PhpMyAdmin\Image\ImageWrapper;
use TCPDF;

use function defined;
use function explode;
use function floatval;
use function mb_strripos;
use function mb_substr;
use function mt_getrandmax;
use function preg_match;
use function random_int;
use function sprintf;
use function str_replace;
use function strtoupper;
use function trim;

/**
 * Base class for all GIS data type classes.
 */
abstract class GisGeometry
{
    /**
     * Prepares and returns the code related to a row in the GIS dataset as SVG.
     *
     * @param string $spatial    GIS data object
     * @param string $label      label for the GIS data object
     * @param int[]  $color      color for the GIS data object
     * @param array  $scale_data data related to scaling
     *
     * @return string the code related to a row in the GIS dataset
     */
    abstract public function prepareRowAsSvg($spatial, string $label, array $color, array $scale_data): string;

    /**
     * Adds to the PNG image object, the data related to a row in the GIS dataset.
     *
     * @param string $spatial    GIS POLYGON object
     * @param string $label      Label for the GIS POLYGON object
     * @param int[]  $color      Color for the GIS POLYGON object
     * @param array  $scale_data Array containing data related to scaling
     */
    abstract public function prepareRowAsPng(
        $spatial,
        string $label,
        array $color,
        array $scale_data,
        ImageWrapper $image,
    ): ImageWrapper;

    /**
     * Adds to the TCPDF instance, the data related to a row in the GIS dataset.
     *
     * @param string $spatial    GIS data object
     * @param string $label      label for the GIS data object
     * @param int[]  $color      color for the GIS data object
     * @param array  $scale_data array containing data related to scaling
     * @param TCPDF  $pdf
     *
     * @return TCPDF the modified TCPDF instance
     */
    abstract public function prepareRowAsPdf(
        $spatial,
        string $label,
        array $color,
        array $scale_data,
        $pdf,
    ): TCPDF;

    /**
     * Prepares the JavaScript related to a row in the GIS dataset
     * to visualize it with OpenLayers.
     *
     * @param string $spatial    GIS data object
     * @param int    $srid       spatial reference ID
     * @param string $label      label for the GIS data object
     * @param int[]  $color      color for the GIS data object
     * @param array  $scale_data array containing data related to scaling
     *
     * @return string the JavaScript related to a row in the GIS dataset
     */
    abstract public function prepareRowAsOl(
        $spatial,
        int $srid,
        string $label,
        array $color,
        array $scale_data,
    ): string;

    /**
     * Scales each row.
     *
     * @param string $spatial spatial data of a row
     *
     * @return ScaleData|null min, max values for x and y coordinates
     */
    abstract public function scaleRow(string $spatial): ScaleData|null;

    /**
     * Generates the WKT with the set of parameters passed by the GIS editor.
     *
     * @param array       $gis_data GIS data
     * @param int         $index    index into the parameter object
     * @param string|null $empty    value for empty points
     *
     * @return string WKT with the set of parameters passed by the GIS editor
     */
    abstract public function generateWkt(array $gis_data, $index, $empty = ''): string;

    /**
     * Returns OpenLayers.Bounds object that correspond to the bounds of GIS data.
     *
     * @param int   $srid       spatial reference ID
     * @param array $scale_data data related to scaling
     *
     * @return string OpenLayers.Bounds object that
     *                correspond to the bounds of GIS data
     */
    protected function getBoundsForOl(int $srid, array $scale_data): string
    {
        return sprintf(
            'var minLoc = [%s, %s];'
            . 'var maxLoc = [%s, %s];'
            . 'var ext = ol.extent.boundingExtent([minLoc, maxLoc]);'
            . 'ext = ol.proj.transformExtent(ext, ol.proj.get("EPSG:%s"), ol.proj.get(\'EPSG:3857\'));'
            . 'map.getView().fit(ext, map.getSize());',
            $scale_data['minX'],
            $scale_data['minY'],
            $scale_data['maxX'],
            $scale_data['maxY'],
            $srid,
        );
    }

    /**
     * Updates the min, max values with the given point set.
     *
     * @param string         $point_set point set
     * @param ScaleData|null $scaleData existing min, max values
     *
     * @return ScaleData|null the updated min, max values
     */
    protected function setMinMax(string $point_set, ScaleData|null $scaleData = null): ScaleData|null
    {
        // Separate each point
        $points = explode(',', $point_set);

        foreach ($points as $point) {
            // Extract coordinates of the point
            $coordinates = explode(' ', $point);

            $x = (float) $coordinates[0];
            $y = (float) $coordinates[1];

            $scaleData = $scaleData === null ? new ScaleData($x, $x, $y, $y) : $scaleData->expand($x, $y);
        }

        return $scaleData;
    }

    /**
     * Parses the wkt and optional srid from a combined string for the GIS data editor
     *
     * @param string $value value of the GIS column
     *
     * @return array<string,int|string> parameters for the GIS editor from the value of the GIS column
     * @psalm-return array{'srid':int,'wkt':string}
     */
    protected function parseWktAndSrid(string $value): array
    {
        $geom_types = '(POINT|MULTIPOINT|LINESTRING|MULTILINESTRING|POLYGON|MULTIPOLYGON|GEOMETRYCOLLECTION)';
        $srid = 0;
        $wkt = '';

        if (preg_match("/^'" . $geom_types . "\(.*\)',[0-9]*$/i", $value)) {
            $last_comma = mb_strripos($value, ',');
            $srid = (int) trim(mb_substr($value, $last_comma + 1));
            $wkt = trim(mb_substr($value, 1, $last_comma - 2));
        } elseif (preg_match('/^' . $geom_types . '\(.*\)$/i', $value)) {
            $wkt = $value;
        }

        return [
            'srid' => $srid,
            'wkt' => $wkt,
        ];
    }

    /**
     * Generate coordinate parameters for the GIS data editor from the value of the GIS column.
     *
     * @param string $wkt Value of the GIS column
     *
     * @return array Coordinate params for the GIS data editor from the value of the GIS column
     */
    abstract protected function getCoordinateParams(string $wkt): array;

    /**
     * Generate parameters for the GIS data editor from the value of the GIS column.
     *
     * @param string $value Value of the GIS column
     *
     * @return array params for the GIS data editor from the value of the GIS column
     */
    public function generateParams(string $value): array
    {
        $data = $this->parseWktAndSrid($value);
        $index = 0;
        $wkt = $data['wkt'];
        preg_match('/^\w+/', $wkt, $matches);
        $wkt_type = strtoupper($matches[0]);

        return [
            'srid' => $data['srid'],
            $index => [
                $wkt_type => $this->getCoordinateParams($wkt),
            ],
        ];
    }

    /**
     * Extracts points, scales and returns them as an array.
     *
     * @param string     $point_set  string of comma separated points
     * @param array|null $scale_data data related to scaling
     * @param bool       $linear     if true, as a 1D array, else as a 2D array
     *
     * @return array scaled points
     */
    protected function extractPoints($point_set, $scale_data, $linear = false): array
    {
        $points_arr = [];

        // Separate each point
        $points = explode(',', $point_set);

        foreach ($points as $point) {
            $point = str_replace(['(', ')'], '', $point);
            // Extract coordinates of the point
            $coordinates = explode(' ', $point);

            if (isset($coordinates[0], $coordinates[1]) && trim($coordinates[0]) != '' && trim($coordinates[1]) != '') {
                if ($scale_data != null) {
                    $x = ($coordinates[0] - $scale_data['x']) * $scale_data['scale'];
                    $y = $scale_data['height']
                        - ($coordinates[1] - $scale_data['y']) * $scale_data['scale'];
                } else {
                    $x = floatval(trim($coordinates[0]));
                    $y = floatval(trim($coordinates[1]));
                }
            } else {
                $x = 0;
                $y = 0;
            }

            if (! $linear) {
                $points_arr[] = [
                    $x,
                    $y,
                ];
            } else {
                $points_arr[] = $x;
                $points_arr[] = $y;
            }
        }

        return $points_arr;
    }

    /**
     * Generates JavaScript for adding an array of polygons to OpenLayers.
     *
     * @param array $polygons x and y coordinates for each polygon
     * @param int   $srid     spatial reference id
     *
     * @return string JavaScript for adding an array of polygons to OpenLayers
     */
    protected function getPolygonArrayForOpenLayers(array $polygons, int $srid): string
    {
        $ol_array = 'var polygonArray = [];';
        foreach ($polygons as $polygon) {
            $rings = explode('),(', $polygon);
            $ol_array .= $this->getPolygonForOpenLayers($rings, $srid);
            $ol_array .= 'polygonArray.push(polygon);';
        }

        return $ol_array;
    }

    /**
     * Generates JavaScript for adding points for OpenLayers polygon.
     *
     * @param array $polygon x and y coordinates for each line
     * @param int   $srid    spatial reference id
     *
     * @return string JavaScript for adding points for OpenLayers polygon
     */
    protected function getPolygonForOpenLayers(array $polygon, int $srid): string
    {
        return $this->getLineArrayForOpenLayers($polygon, $srid, false)
        . 'var polygon = new ol.geom.Polygon(arr);';
    }

    /**
     * Generates JavaScript for adding an array of LineString
     * or LineRing to OpenLayers.
     *
     * @param array $lines          x and y coordinates for each line
     * @param int   $srid           spatial reference id
     * @param bool  $is_line_string whether it's an array of LineString
     *
     * @return string JavaScript for adding an array of LineString
     *                or LineRing to OpenLayers
     */
    protected function getLineArrayForOpenLayers(
        array $lines,
        int $srid,
        $is_line_string = true,
    ): string {
        $ol_array = 'var arr = [];';
        foreach ($lines as $line) {
            $ol_array .= 'var lineArr = [];';
            $points_arr = $this->extractPoints($line, null);
            $ol_array .= 'var line = ' . $this->getLineForOpenLayers($points_arr, $srid, $is_line_string) . ';';
            $ol_array .= 'var coord = line.getCoordinates();';
            $ol_array .= 'for (var i = 0; i < coord.length; i++) lineArr.push(coord[i]);';
            $ol_array .= 'arr.push(lineArr);';
        }

        return $ol_array;
    }

    /**
     * Generates JavaScript for adding a LineString or LineRing to OpenLayers.
     *
     * @param array $points_arr     x and y coordinates for each point
     * @param int   $srid           spatial reference id
     * @param bool  $is_line_string whether it's a LineString
     *
     * @return string JavaScript for adding a LineString or LineRing to OpenLayers
     */
    protected function getLineForOpenLayers(
        array $points_arr,
        int $srid,
        $is_line_string = true,
    ): string {
        return 'new ol.geom.'
        . ($is_line_string ? 'LineString' : 'LinearRing') . '('
        . $this->getPointsArrayForOpenLayers($points_arr, $srid)
        . ')';
    }

    /**
     * Generates JavaScript for adding an array of points to OpenLayers.
     *
     * @param array $points_arr x and y coordinates for each point
     * @param int   $srid       spatial reference id
     *
     * @return string JavaScript for adding an array of points to OpenLayers
     */
    protected function getPointsArrayForOpenLayers(array $points_arr, int $srid): string
    {
        $ol_array = 'new Array(';
        foreach ($points_arr as $point) {
            $ol_array .= $this->getPointForOpenLayers($point, $srid) . '.getCoordinates(), ';
        }

        $ol_array = mb_substr($ol_array, 0, -2);

        return $ol_array . ')';
    }

    /**
     * Generates JavaScript for adding a point to OpenLayers.
     *
     * @param array $point array containing the x and y coordinates of the point
     * @param int   $srid  spatial reference id
     *
     * @return string JavaScript for adding points to OpenLayers
     */
    protected function getPointForOpenLayers(array $point, int $srid): string
    {
        return '(new ol.geom.Point([' . $point[0] . ',' . $point[1] . '])'
        . '.transform(ol.proj.get("EPSG:' . $srid . '")'
        . ', ol.proj.get(\'EPSG:3857\')))';
    }

    protected function getRandomId(): int
    {
        return ! defined('TESTSUITE') ? random_int(0, mt_getrandmax()) : 1234567890;
    }
}
