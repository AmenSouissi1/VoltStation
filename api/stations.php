<?php
require_once '../config.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get endpoint parts
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

// Connect to database
try {
    $db = connectDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Handle stations endpoint
if ($method === 'GET') {
    // Query the MongoDB collection for stations
    try {
        $stationsCollection = $db->stations;
        $bornesCollection = $db->bornes;
        
        // Get all stations from database
        $cursor = $stationsCollection->find();
        $stations = [];
        
        foreach ($cursor as $station) {
            // Get bornes for this station
            $bornesCursor = $bornesCollection->find(['station_id' => $station['_id']]);
            $bornes = [];
            
            foreach ($bornesCursor as $borne) {
                $bornes[] = [
                    'id_borne' => (string)$borne['_id'],
                    'type_borne' => $borne['type'],
                    'puissance' => (float)$borne['puissance'],
                    'etat_actuel' => $borne['statut']
                ];
            }
            
            // Format station data
            $formattedStation = [
                'id' => (string)$station['_id'],
                'nom_station' => $station['nom'],
                'adresse' => [
                    'rue' => $station['adresse'],
                    'ville' => explode(',', $station['adresse'])[1] ?? '',
                    'code_postal' => '',
                    'pays' => 'France'
                ],
                'coordonnees' => [
                    'latitude' => $station['coordonnees']['lat'] ?? 48.8566,
                    'longitude' => $station['coordonnees']['lng'] ?? 2.3522
                ],
                'nombre_bornes' => count($bornes),
                'statut' => $station['statut'] ?? 'opérationnelle',
                'date_installation' => $station['date_creation'] ? date('Y-m-d', strtotime($station['date_creation'])) : date('Y-m-d'),
                'gestionnaire' => 'VoltManage SAS',
                'temps_moyen_utilisation' => 45.0,
                'bornes' => $bornes
            ];
            
            $stations[] = $formattedStation;
        }
        
        // If no stations found in the database, provide fallback data
        if (empty($stations)) {
            $stations[] = [
                'id' => 'default',
                'nom_station' => 'Station Paris Centre',
                'adresse' => [
                    'rue' => '15 Rue de Rivoli',
                    'ville' => 'Paris',
                    'code_postal' => '75004',
                    'pays' => 'France'
                ],
                'coordonnees' => [
                    'latitude' => 48.856614,
                    'longitude' => 2.352222
                ],
                'nombre_bornes' => 2,
                'statut' => 'opérationnelle',
                'date_installation' => date('Y-m-d'),
                'gestionnaire' => 'VoltManage SAS',
                'temps_moyen_utilisation' => 45.0,
                'bornes' => [
                    [
                        'id_borne' => 'B001',
                        'type_borne' => 'Rapide',
                        'puissance' => 50.0,
                        'etat_actuel' => 'disponible'
                    ],
                    [
                        'id_borne' => 'B002',
                        'type_borne' => 'Standard',
                        'puissance' => 22.0,
                        'etat_actuel' => 'disponible'
                    ]
                ]
            ];
        }
    } catch (Exception $e) {
        // Log the error and provide fallback data
        error_log("Error retrieving stations: " . $e->getMessage());
        
        $stations = [
            [
                'id' => 'error',
                'nom_station' => 'Station Paris Centre',
                'adresse' => [
                    'rue' => '15 Rue de Rivoli',
                    'ville' => 'Paris',
                    'code_postal' => '75004',
                    'pays' => 'France'
                ],
                'coordonnees' => [
                    'latitude' => 48.856614,
                    'longitude' => 2.352222
                ],
                'nombre_bornes' => 2,
                'statut' => 'opérationnelle',
                'date_installation' => date('Y-m-d'),
                'gestionnaire' => 'VoltManage SAS',
                'temps_moyen_utilisation' => 45.0,
                'bornes' => [
                    [
                        'id_borne' => 'B001',
                        'type_borne' => 'Rapide',
                        'puissance' => 50.0,
                        'etat_actuel' => 'disponible'
                    ],
                    [
                        'id_borne' => 'B002',
                        'type_borne' => 'Standard',
                        'puissance' => 22.0,
                        'etat_actuel' => 'disponible'
                    ]
                ]
            ]
        ];
    }

    // If ID is provided, return specific station
    if ($id) {
        // Try to get directly from database first
        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
            $station = $stationsCollection->findOne(['_id' => $objectId]);
            
            if ($station) {
                // Get bornes for this station
                $bornesCursor = $bornesCollection->find(['station_id' => $station['_id']]);
                $bornes = [];
                
                foreach ($bornesCursor as $borne) {
                    $bornes[] = [
                        'id_borne' => (string)$borne['_id'],
                        'type_borne' => $borne['type'],
                        'puissance' => (float)$borne['puissance'],
                        'etat_actuel' => $borne['statut']
                    ];
                }
                
                // Format station data
                $formattedStation = [
                    'id' => (string)$station['_id'],
                    'nom_station' => $station['nom'],
                    'adresse' => [
                        'rue' => $station['adresse'],
                        'ville' => explode(',', $station['adresse'])[1] ?? '',
                        'code_postal' => '',
                        'pays' => 'France'
                    ],
                    'coordonnees' => [
                        'latitude' => $station['coordonnees']['lat'] ?? 48.8566,
                        'longitude' => $station['coordonnees']['lng'] ?? 2.3522
                    ],
                    'nombre_bornes' => count($bornes),
                    'statut' => $station['statut'] ?? 'opérationnelle',
                    'date_installation' => $station['date_creation'] ? date('Y-m-d', strtotime($station['date_creation'])) : date('Y-m-d'),
                    'gestionnaire' => 'VoltManage SAS',
                    'temps_moyen_utilisation' => 45.0,
                    'bornes' => $bornes
                ];
                
                echo json_encode($formattedStation);
                exit;
            }
        } catch (Exception $e) {
            // If error (like invalid ObjectId), continue with fallback
            error_log("Error retrieving station by ID: " . $e->getMessage());
        }
        
        // Fallback to pre-loaded stations array
        $found = false;
        foreach ($stations as $station) {
            if ($station['id'] === $id) {
                echo json_encode($station);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Station not found']);
        }
    } 
    // If action is available, return available bornes
    elseif ($action === 'available') {
        $availableStations = [];
        foreach ($stations as $station) {
            $availableBornes = 0;
            foreach ($station['bornes'] as $borne) {
                if ($borne['etat_actuel'] === 'disponible') {
                    $availableBornes++;
                }
            }
            
            $availableStations[] = [
                'id' => $station['id'],
                'name' => $station['nom_station'],
                'address' => $station['adresse']['rue'] . ', ' . ($station['adresse']['code_postal'] ? $station['adresse']['code_postal'] . ' ' : '') . $station['adresse']['ville'],
                'coordinates' => [$station['coordonnees']['latitude'], $station['coordonnees']['longitude']],
                'available_bornes' => $availableBornes,
                'total_bornes' => count($station['bornes'])
            ];
        }
        
        echo json_encode($availableStations);
    }
    // Otherwise return all stations
    else {
        echo json_encode($stations);
    }
} elseif ($method === 'POST') {
    // In a real app, this would create a new station in MongoDB
    // For demo, just return success message
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }
    
    // Validate required fields
    if (!isset($data['nom_station']) || !isset($data['adresse']) || !isset($data['coordonnees'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    // Return success with generated ID
    $newId = 'ST' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    echo json_encode([
        'success' => true,
        'message' => 'Station created successfully',
        'id' => $newId
    ]);
} elseif ($method === 'PUT') {
    // In a real app, this would update a station in MongoDB
    // For demo, just return success message
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Station ID is required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Station updated successfully',
        'id' => $id
    ]);
} elseif ($method === 'DELETE') {
    // In a real app, this would delete a station from MongoDB
    // For demo, just return success message
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Station ID is required']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Station deleted successfully',
        'id' => $id
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}