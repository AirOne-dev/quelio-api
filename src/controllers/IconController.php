<?php

class IconController
{
    /**
     * Generate dynamic SVG icon
     * GET /icon.svg
     */
    public function indexAction(): void
    {
        // Get colors from query parameters, with ocean theme as default
        $primary = $_GET['primary'] ?? '0EA5E9';
        $secondary = $_GET['secondary'] ?? '38BDF8';

        // Sanitize colors (remove # if present and validate hex)
        $primary = ltrim($primary, '#');
        $secondary = ltrim($secondary, '#');

        // Validate hex colors (6 characters, alphanumeric)
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $primary)) {
            $primary = '0EA5E9';
        }
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $secondary)) {
            $secondary = '38BDF8';
        }

        // Add # prefix for SVG
        $primary = '#' . $primary;
        $secondary = '#' . $secondary;

        // Set headers for SVG
        header('Content-Type: image/svg+xml');
        header('Cache-Control: no-cache, must-revalidate');

        // Generate SVG with gradient using theme colors
        $this->renderSvg($primary, $secondary);
    }

    /**
     * Render the SVG icon
     */
    private function renderSvg(string $primary, string $secondary): void
    {
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<svg width="581" height="580" viewBox="0 0 581 580" fill="none" xmlns="http://www.w3.org/2000/svg">
<rect x="0.5" width="580" height="580" fill="black"/>
<rect x="0.5" width="580" height="580" fill="url(#paint0_linear_126_88)"/>
<mask id="mask0_126_88" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="580" height="580">
<path d="M580 0V580H0V0H580ZM245.5 64.5C120.96 64.5 20 165.46 20 290C20 414.54 120.96 515.5 245.5 515.5C370.04 515.5 471 414.54 471 290C471 165.46 370.04 64.5 245.5 64.5ZM245.5 169.5C312.05 169.5 366 223.45 366 290C366 356.55 312.05 410.5 245.5 410.5C178.95 410.5 125 356.55 125 290C125 223.45 178.95 169.5 245.5 169.5Z" fill="white"/>
</mask>
<g mask="url(#mask0_126_88)">
<g filter="url(#filter0_d_126_88)">
<path d="M508.5 462.5H436.053C422.59 462.5 409.696 457.071 400.288 447.441L259.735 303.571C250.609 294.23 245.5 281.689 245.5 268.63V187" stroke="white" stroke-opacity="0.85" stroke-width="35" stroke-linecap="round" shape-rendering="crispEdges"/>
</g>
</g>
<g filter="url(#filter1_d_126_88)">
<circle cx="245.5" cy="290" r="173" stroke="white" stroke-opacity="0.85" stroke-width="35" shape-rendering="crispEdges"/>
</g>
<defs>
<filter id="filter0_d_126_88" x="208" y="149.5" width="338" height="350.5" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
<feOffset/>
<feGaussianBlur stdDeviation="10"/>
<feComposite in2="hardAlpha" operator="out"/>
<feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.5 0"/>
<feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_126_88"/>
<feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_126_88" result="shape"/>
</filter>
<filter id="filter1_d_126_88" x="35" y="79.5" width="421" height="421" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
<feOffset/>
<feGaussianBlur stdDeviation="10"/>
<feComposite in2="hardAlpha" operator="out"/>
<feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.5 0"/>
<feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_126_88"/>
<feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_126_88" result="shape"/>
</filter>
<linearGradient id="paint0_linear_126_88" x1="203" y1="186" x2="580.5" y2="580" gradientUnits="userSpaceOnUse">
<stop stop-color="<?php echo $primary; ?>"/>
<stop offset="1" stop-color="<?php echo $secondary; ?>" stop-opacity="0.5"/>
</linearGradient>
</defs>
</svg>
        <?php
    }
}
