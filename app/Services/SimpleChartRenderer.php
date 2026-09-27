<?php

namespace App\Services;

/**
 * Small GD-drawn chart images for the PDF report -- dompdf can't execute
 * JS (so Chart.js, used on the live web dashboard, isn't an option here),
 * and GD is already a dependency of this app (ReportController's overlay-
 * image downscaling). Hand-rolled rather than pulling in a charting
 * library for two simple chart shapes.
 *
 * Returns base64 data URIs directly, matching
 * ReportController::downscaledImageDataUri()'s existing convention for
 * embedding images in the PDF view without touching disk.
 */
class SimpleChartRenderer
{
    private const FONT = 3; // GD built-in bitmap font -- no TTF file dependency

    /**
     * One horizontal bar per item. $items: list of
     * ['label' => string, 'value' => int|float, 'color' => [r, g, b]].
     */
    public function horizontalBarChart(array $items, int $width = 560): string
    {
        $rowHeight = 34;
        $paddingLeft = 150;
        $paddingRight = 60;
        $paddingTop = 10;
        $paddingBottom = 10;
        $barAreaWidth = $width - $paddingLeft - $paddingRight;
        $height = $paddingTop + $paddingBottom + count($items) * $rowHeight;

        $maxValue = max(1, ...array_map(fn ($i) => (float) $i['value'], $items));

        $image = imagecreatetruecolor($width, max($height, 1));
        $white = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 33, 37, 41);
        imagefill($image, 0, 0, $white);

        foreach ($items as $index => $item) {
            $y = $paddingTop + $index * $rowHeight;
            $barHeight = $rowHeight - 12;
            $barWidth = (int) round(($item['value'] / $maxValue) * $barAreaWidth);
            [$r, $g, $b] = $item['color'];
            $barColor = imagecolorallocate($image, $r, $g, $b);
            $trackColor = imagecolorallocate($image, 233, 236, 239);

            imagefilledrectangle($image, $paddingLeft, $y, $paddingLeft + $barAreaWidth, $y + $barHeight, $trackColor);
            if ($barWidth > 0) {
                imagefilledrectangle($image, $paddingLeft, $y, $paddingLeft + $barWidth, $y + $barHeight, $barColor);
            }

            $labelY = (int) round($y + ($barHeight - 13) / 2);
            imagestring($image, self::FONT, 4, $labelY, (string) $item['label'], $textColor);
            imagestring($image, self::FONT, $paddingLeft + $barAreaWidth + 6, $labelY, (string) $item['value'], $textColor);
        }

        return $this->toDataUri($image);
    }

    /**
     * One row per parameter, showing its min-avg-max range as a horizontal
     * track with a marker at the average -- a compact stand-in for a full
     * histogram, since the PDF only carries min/avg/max (see
     * ReportDataAggregator), not every raw reading.
     */
    public function rangeChart(array $parameters, int $width = 560): string
    {
        $rowHeight = 40;
        $paddingLeft = 150;
        $paddingRight = 20;
        $paddingTop = 10;
        $paddingBottom = 10;
        $trackWidth = $width - $paddingLeft - $paddingRight;
        $rows = array_values(array_filter($parameters, fn ($p) => $p['count'] > 0));
        $height = $paddingTop + $paddingBottom + max(count($rows), 1) * $rowHeight;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 33, 37, 41);
        $trackColor = imagecolorallocate($image, 222, 226, 230);
        $rangeColor = imagecolorallocate($image, 45, 106, 79);
        $avgColor = imagecolorallocate($image, 240, 165, 0);
        imagefill($image, 0, 0, $white);

        if (empty($rows)) {
            imagestring($image, self::FONT, 4, $paddingTop, 'No measurements recorded yet.', $textColor);
            return $this->toDataUri($image);
        }

        foreach ($rows as $index => $param) {
            $y = $paddingTop + $index * $rowHeight + (int) ($rowHeight / 2);
            $min = (float) $param['min'];
            $max = (float) $param['max'];
            $avg = (float) $param['avg'];
            $span = max($max - $min, 0.0001);

            imagestring($image, self::FONT, 4, $y - 7, $param['label'], $textColor);

            imageline($image, $paddingLeft, $y, $paddingLeft + $trackWidth, $y, $trackColor);
            $minX = $paddingLeft;
            $maxX = $paddingLeft + $trackWidth;
            imagefilledellipse($image, $minX, $y, 6, 6, $rangeColor);
            imagefilledellipse($image, $maxX, $y, 6, 6, $rangeColor);
            imageline($image, $minX, $y, $maxX, $y, $rangeColor);

            $avgX = (int) round($paddingLeft + (($avg - $min) / $span) * $trackWidth);
            imagefilledellipse($image, $avgX, $y, 10, 10, $avgColor);

            $rangeLabel = sprintf('%.2f - %.2f (avg %.2f)', $min, $max, $avg);
            imagestring($image, 2, $paddingLeft, $y + 8, $rangeLabel, $textColor);
        }

        return $this->toDataUri($image);
    }

    private function toDataUri($image): string
    {
        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($pngData);
    }
}
