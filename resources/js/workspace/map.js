// Only loaded on routes with a map panel (aoi -- which now covers both AOI
// drawing and drone mission planning -- / orthomosaic -- see $showMapStack
// in layouts/workspace.blade.php). Depends on the
// Leaflet, Leaflet-Draw, Turf, and georaster CDN globals, which are loaded
// as classic <script> tags before this module executes.
import './drone-mission-panel.js';
import './map-workspace.js';
