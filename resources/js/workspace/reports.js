// Only loaded on the Reports route (see $isReportsRoute in
// layouts/workspace.blade.php). Depends on the Chart.js CDN global, loaded
// as a classic <script> tag before this module executes -- same pattern
// map.js uses for Leaflet/Turf/georaster.
import './reports-dashboard.js';
