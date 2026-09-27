// Loaded on every workspace route. Keep this free of the Leaflet/Turf/
// georaster stack -- that only loads via map.js, and only on routes that
// actually show a map panel (see the $showMapStack check in
// layouts/workspace.blade.php).
import './theme-toggle.js';
import './ui-sound.js';
import './sidebar.js';
