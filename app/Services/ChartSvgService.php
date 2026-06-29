<?php

namespace App\Services;

/**
 * Server-side SVG chart generator for PDF embedding.
 * DomPDF can render inline SVG natively — no Browsershot needed.
 */
class ChartSvgService
{
    /**
     * Donut/Ring completion gauge.
     */
    public static function donutGauge(float $percentage, string $label = '', int $size = 180): string
    {
        $cx = $size / 2;
        $cy = $size / 2;
        $radius = $size / 2 - 16;
        $stroke = 14;

        $angle = ($percentage / 100) * 360;
        $color = $percentage >= 80 ? '#10b981' : ($percentage >= 50 ? '#f59e0b' : '#ef4444');

        $arc = self::describeArc($cx, $cy, $radius - $stroke / 2, -90, -90 + $angle);
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$size} {$size}" width="{$size}" height="{$size}">
  <circle cx="{$cx}" cy="{$cy}" r="{$radius}" fill="none" stroke="#e5e7eb" stroke-width="{$stroke}"/>
  <path d="{$arc}" fill="none" stroke="{$color}" stroke-width="{$stroke}" stroke-linecap="round"/>
  <text x="{$cx}" y="{$cy}" text-anchor="middle" dominant-baseline="central" font-size="28" font-weight="700" fill="#111827">
    {$percentage}%
  </text>
  <text x="{$cx}" y="{$cy}+24" text-anchor="middle" font-size="11" fill="#6b7280">
    {$safeLabel}
  </text>
</svg>
SVG;
    }

    /**
     * Horizontal bar chart for task status distribution.
     */
    public static function horizontalBars(array $items, int $width = 400, int $barHeight = 28, int $gap = 8): string
    {
        // items: [['label' => 'Done', 'value' => 12, 'color' => '#10b981'], ...]
        $maxVal = max(array_column($items, 'value')) ?: 1;
        $chartW = $width - 120; // left label space + right value
        $count = count($items);
        $totalH = $count * ($barHeight + $gap) + 32;
        $labelX = 8;
        $barX = 80;
        $valX = $barX + $chartW + 8;

        $bars = '';
        $y = 32;
        foreach ($items as $item) {
            $w = ($item['value'] / $maxVal) * $chartW;
            $w = max($w, $item['value'] > 0 ? 6 : 0);
            $color = $item['color'] ?? '#6b7280';
            $safeLabel = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            $bars .= <<<BAR
  <text x="{$labelX}" y="{$y}" dominant-baseline="central" font-size="12" fill="#374151" font-weight="500">{$safeLabel}</text>
  <rect x="{$barX}" y="{$y}" width="{$w}" height="{$barHeight}" rx="4" fill="{$color}" opacity="0.85"/>
  <text x="{$valX}" y="{$y}" dominant-baseline="central" font-size="12" fill="#6b7280">{$item['value']}</text>
BAR;
            $y += $barHeight + $gap;
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$totalH}" width="{$width}" height="{$totalH}">
{$bars}
</svg>
SVG;
    }

    /**
     * Mini summary card with trend arrow.
     */
    public static function kpiCard(string $title, string $value, string $trend = '', string $color = '#2563eb'): string
    {
        $trendArrow = match ($trend) {
            'up' => '↑', 'down' => '↓', default => '→',
        };
        $trendColor = $trend === 'up' ? '#10b981' : ($trend === 'down' ? '#ef4444' : '#6b7280');

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 80" width="160" height="80">
  <rect x="0" y="0" width="160" height="80" rx="10" fill="#f9fafb" stroke="#e5e7eb" stroke-width="1"/>
  <text x="16" y="28" font-size="12" fill="#6b7280">{$safeTitle}</text>
  <text x="16" y="56" font-size="22" font-weight="700" fill="{$color}">{$safeValue}</text>
  <text x="140" y="28" text-anchor="end" font-size="14" fill="{$trendColor}">{$trendArrow}</text>
</svg>
SVG;
    }

    /**
     * Grouped horizontal bars — multi-project comparison.
     * $groups: [ ['name' => '项目A', 'bars' => [['label'=>'完成','value'=>12,'color'=>'#4f46e5'], ...]], ... ]
     * Each group gets one column of bars side by side.
     */
    public static function groupedBars(array $groups, string $title = '', int $barWidth = 36, int $barHeight = 140, int $gap = 12): string
    {
        $palette = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4'];
        $groupCount = count($groups);
        if ($groupCount === 0) {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60"><text x="100" y="35" text-anchor="middle" font-size="12" fill="#9ca3af">请选择项目</text></svg>';
        }

        // Find all unique labels across all groups
        $allLabels = [];
        foreach ($groups as $gi => $g) {
            foreach ($g['bars'] as $bar) {
                $allLabels[$bar['label']] = true;
            }
        }
        $labels = array_keys($allLabels);
        $labelCount = count($labels);

        // Calculate dimensions
        $labelW = 64; // left-side label width
        $groupAreaW = $barWidth + 16; // bar + value text
        $chartInnerW = $labelW + ($groupCount * $groupAreaW) + 24;
        $barAreaH = $barHeight + 28; // bar + bottom label
        $chartH = ($labelCount * $barAreaH) + 80; // + header/footer

        $svgW = max(320, $chartInnerW + 40);

        $bars = '';
        $maxVal = 1;
        foreach ($groups as $g) {
            foreach ($g['bars'] as $b) {
                if ($b['value'] > $maxVal) {
                    $maxVal = $b['value'];
                }
            }
        }

        // Build label → value map per group
        foreach ($labels as $li => $label) {
            $yBase = 60 + ($li * $barAreaH);

            // Left label
            $bars .= sprintf(
                '<text x="%d" y="%d" text-anchor="end" font-size="11" fill="%s" font-weight="500">%s</text>',
                8, $yBase + $barHeight / 2 + 4, '#374151', htmlspecialchars($label)
            );

            foreach ($groups as $gi => $group) {
                $color = $group['color'] ?? $palette[$gi % count($palette)];
                $xOff = $labelW + 8 + ($gi * $groupAreaW);
                $value = 0;
                foreach ($group['bars'] as $bar) {
                    if ($bar['label'] === $label) {
                        $value = $bar['value'];
                        break;
                    }
                }
                $h = ($value / $maxVal) * $barHeight;
                $h = max($h, $value > 0 ? 4 : 0);
                $barY = $yBase + ($barHeight - $h);

                $bars .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" rx="4" fill="%s" opacity="0.85"/>',
                    $xOff, $barY, $barWidth, $h, $color
                );
                $bars .= sprintf(
                    '<text x="%d" y="%d" text-anchor="middle" font-size="10" fill="%s" font-weight="600">%d</text>',
                    $xOff + $barWidth / 2, $barY - 6, $color, $value
                );
            }
        }

        // Legend row
        $legendY = 24;
        $legendItems = '';
        foreach ($groups as $gi => $group) {
            $color = $group['color'] ?? $palette[$gi % count($palette)];
            $lx = $labelW + 8 + ($gi * $groupAreaW);
            $legendItems .= sprintf(
                '<rect x="%d" y="%d" width="10" height="10" rx="2" fill="%s" opacity="0.85"/>',
                $lx, $legendY - 8, $color
            );
            $legendItems .= sprintf(
                '<text x="%d" y="%d" font-size="10" fill="%s" font-weight="500">%s</text>',
                $lx + 14, $legendY + 1, '#374151', htmlspecialchars(mb_strlen($group['name']) > 8 ? mb_substr($group['name'], 0, 7).'…' : $group['name'])
            );
        }

        $titleEl = $title ? sprintf('<text x="%d" y="%d" text-anchor="start" font-size="13" fill="#374151" font-weight="600">%s</text>', 8, $legendY, htmlspecialchars($title)) : '';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$svgW} {$chartH}" width="{$svgW}" height="{$chartH}">
  <rect x="0" y="0" width="{$svgW}" height="{$chartH}" fill="transparent"/>
  {$titleEl}
  {$legendItems}
  {$bars}
</svg>
SVG;
    }

    /**
     * Simple progress bar with label.
     */
    public static function progressBar(float $percentage, string $label = '', int $width = 300, int $height = 40): string
    {
        $color = $percentage >= 80 ? '#10b981' : ($percentage >= 50 ? '#f59e0b' : '#ef4444');
        $barW = ($percentage / 100) * ($width - 16);
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}" width="{$width}" height="{$height}">
  <rect x="0" y="10" width="{$width}" height="20" rx="6" fill="#e5e7eb"/>
  <rect x="0" y="10" width="{$barW}" height="20" rx="6" fill="{$color}" opacity="0.85"/>
  <text x="8" y="26" font-size="11" fill="#374151" font-weight="600">{$safeLabel} {$percentage}%</text>
</svg>
SVG;
    }

    // ---------- private helpers ----------

    /**
     * SVG arc path for donut charts.
     */
    private static function describeArc(float $cx, float $cy, float $r, float $startDeg, float $endDeg): string
    {
        $start = deg2rad($startDeg);
        $end = deg2rad($endDeg);

        $x1 = $cx + $r * cos($start);
        $y1 = $cy + $r * sin($start);
        $x2 = $cx + $r * cos($end);
        $y2 = $cy + $r * sin($end);

        $largeArc = ($endDeg - $startDeg) > 180 ? 1 : 0;

        return "M {$x1} {$y1} A {$r} {$r} 0 {$largeArc} 1 {$x2} {$y2}";
    }
}
