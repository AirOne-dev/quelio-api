<?php

// Get colors from query parameters, with midnight theme as default
$primary = $_GET['primary'] ?? '4F46E5';
$secondary = $_GET['secondary'] ?? '6366F1';

// Sanitize colors (remove # if present and validate hex)
$primary = ltrim($primary, '#');
$secondary = ltrim($secondary, '#');

// Validate hex colors (6 characters, alphanumeric)
if (!preg_match('/^[0-9A-Fa-f]{6}$/', $primary)) {
    $primary = '4F46E5';
}
if (!preg_match('/^[0-9A-Fa-f]{6}$/', $secondary)) {
    $secondary = '6366F1';
}

// Add # prefix for SVG
$primary = '#' . $primary;
$secondary = '#' . $secondary;

// Set headers for SVG
header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache, must-revalidate');

// Generate SVG with gradient using theme colors
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="iconGradient" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:<?php echo $primary; ?>;stop-opacity:1" />
      <stop offset="100%" style="stop-color:<?php echo $secondary; ?>;stop-opacity:1" />
    </linearGradient>
  </defs>

  <!-- Rounded square background with gradient -->
  <rect width="512" height="512" rx="112" ry="112" fill="url(#iconGradient)"/>

  <!-- Clock icon in white -->
  <g transform="translate(256, 256)">
    <circle cx="0" cy="0" r="140" fill="none" stroke="white" stroke-width="20"/>
    <line x1="0" y1="0" x2="0" y2="-80" stroke="white" stroke-width="16" stroke-linecap="round"/>
    <line x1="0" y1="0" x2="60" y2="60" stroke="white" stroke-width="16" stroke-linecap="round"/>
  </g>
</svg>
