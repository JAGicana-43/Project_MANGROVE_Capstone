// Only loaded on routes with a map panel (aoi / drone-mission / orthomosaic
// -- see $showMapStack in layouts/workspace.blade.php). Depends on the
// Leaflet, Leaflet-Draw, Turf, and georaster CDN globals, which are loaded
// as classic <script> tags before this module executes.
import './drone-mission-panel.js';
import './map-workspace.js';
