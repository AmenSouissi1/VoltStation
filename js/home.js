/**
 * VoltStation - Home Page JavaScript
 * Handles home page specific functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load statistics for the homepage
    loadHomeStatistics();
});

/**
 * Load and display statistics on the homepage
 */
async function loadHomeStatistics() {
    try {
        // In a real application, this would fetch data from the API
        // For now, we'll simulate it with setTimeout to mimic network request
        setTimeout(() => {
            // Sample data - in a real app this would come from the server
            updateStatistics({
                activeStations: 42,
                availableBornes: 87,
                todaySessions: 156,
                savedKwh: 1250.75
            });
        }, 800);
    } catch (error) {
        console.error('Error loading home statistics:', error);
    }
}

/**
 * Update statistics display on the homepage
 * @param {Object} data - Statistics data
 */
function updateStatistics(data) {
    const activeStations = document.getElementById('active-stations');
    const availableBornes = document.getElementById('available-bornes');
    const todaySessions = document.getElementById('today-sessions');
    const savedKwh = document.getElementById('saved-kwh');
    
    if (activeStations) activeStations.textContent = data.activeStations;
    if (availableBornes) availableBornes.textContent = data.availableBornes;
    if (todaySessions) todaySessions.textContent = data.todaySessions;
    if (savedKwh) savedKwh.textContent = data.savedKwh.toFixed(2);
    
    // Add animation effect to make the number update more visually appealing
    const statElements = document.querySelectorAll('#active-stations, #available-bornes, #today-sessions, #saved-kwh');
    statElements.forEach(element => {
        element.classList.add('stat-updated');
        setTimeout(() => {
            element.classList.remove('stat-updated');
        }, 1000);
    });
}

/**
 * Initialize map if it exists on the homepage
 */
function initHomeMap() {
    const mapElement = document.getElementById('home-map');
    if (!mapElement) return;
    
    // Create map centered on a default location (France)
    const map = L.map('home-map').setView([46.603354, 1.888334], 6);
    
    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Sample data for stations - in a real app this would come from the API
    const stations = [
        { name: "Station Paris Centre", lat: 48.8566, lng: 2.3522, status: "operational", bornes: 12 },
        { name: "Station Lyon", lat: 45.7578, lng: 4.8320, status: "operational", bornes: 8 },
        { name: "Station Marseille", lat: 43.2965, lng: 5.3698, status: "maintenance", bornes: 6 },
        { name: "Station Bordeaux", lat: 44.8378, lng: -0.5792, status: "operational", bornes: 10 },
        { name: "Station Lille", lat: 50.6292, lng: 3.0573, status: "out-of-service", bornes: 4 }
    ];
    
    // Define marker icons for different station statuses
    const icons = {
        operational: L.divIcon({
            className: 'station-marker operational',
            html: '<i class="fas fa-charging-station"></i>',
            iconSize: [30, 30]
        }),
        maintenance: L.divIcon({
            className: 'station-marker maintenance',
            html: '<i class="fas fa-tools"></i>',
            iconSize: [30, 30]
        }),
        'out-of-service': L.divIcon({
            className: 'station-marker out-of-service',
            html: '<i class="fas fa-exclamation-triangle"></i>',
            iconSize: [30, 30]
        })
    };
    
    // Add markers for each station
    stations.forEach(station => {
        const marker = L.marker([station.lat, station.lng], {
            icon: icons[station.status]
        }).addTo(map);
        
        // Add popup with station info
        marker.bindPopup(`
            <strong>${station.name}</strong><br>
            Status: <span class="status-${station.status}">${station.status}</span><br>
            Bornes: ${station.bornes}<br>
            <a href="/stations/${station.name.toLowerCase().replace(/\s+/g, '-')}">Voir détails</a>
        `);
    });
}